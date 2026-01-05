<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SyncGlpiTickets extends Command
{
    protected $signature = 'app:sync-glpi-tickets';
    protected $description = 'Sync pending IT tickets from GLPI to create job purchase orders';

    public function handle()
    {
        $this->info('🚀 Starting GLPI ticket synchronization...');
        Log::info('SyncGlpiTickets: Starting job.');

        // Get the default job requester ID from settings
        $jobRequesterId = Setting::where('key', 'automation_job_requester_id')->value('value');

        // Fallback if not set: find the first user in the 'Admin' group
        if (!$jobRequesterId) {
            $this->warn('Default Job Requester is not set in settings. Falling back to the first Admin user.');
            Log::warning('SyncGlpiTickets: Default Job Requester (automation_job_requester_id) not set. Falling back to Admin.');
            
            // Correct way to find an admin user via relationships
            $firstAdmin = User::whereHas('serviceUserRole.userGroup', function ($query) {
                $query->where('slug', 'admin');
            })->first();

            $jobRequesterId = $firstAdmin ? $firstAdmin->id : null;
        }

        if (!$jobRequesterId) {
            $this->error('CRITICAL: No default Job Requester is set and no Admin user found. GLPI POs will be created without a requester.');
            Log::error('SyncGlpiTickets: CRITICAL - No default Job Requester or fallback Admin found.');
        } else {
            $this->info("Using Requester ID: {$jobRequesterId}");
        }
        
        // --- PART 1: Sync from IT Database (Offset 0) ---
        // IT: No specific type filter requested, keeping default behaviors
        $this->syncFromConnection('glpi_it', 0, $jobRequesterId);
        $this->cleanupResolvedTickets('glpi_it', 0);

        // --- PART 2: Sync from EN Database (Offset ID + 1,000,000) ---
        // EN: User requested ONLY 'Request' type (Type 2)
        $this->syncFromConnection('glpi_en', 1000000, $jobRequesterId, 2);
        $this->cleanupResolvedTickets('glpi_en', 1000000);

        $this->info('✨ Synchronization complete.');
        return 0;
    }

    private function syncFromConnection($connectionName, $idOffset, $defaultRequesterId, $ticketType = null)
    {
        $this->line("--- Syncing from <fg=yellow>{$connectionName}</> (Offset: {$idOffset}) " . ($ticketType ? "[Type: {$ticketType}]" : "") . " ---");

        try {
            // Get IDs that already exist for this "range"
            $existingTicketIds = PurchaseOrder::withTrashed() // ✅ Include soft-deleted records!
                ->whereNotNull('glpi_ticket_id')
                ->pluck('glpi_ticket_id')
                ->toArray();

            $query = DB::connection($connectionName)
                ->table('glpi_tickets as t')
                ->join('glpi_tickets_users as tu', 't.id', '=', 'tu.tickets_id')
                ->join('glpi_users as u', 'tu.users_id', '=', 'u.id')
                ->whereIn('t.status', [2, 4])  // ✅ Status 2 (Assigned/Processing) OR 4 (Pending)
                ->where('tu.type', 1)          // Requester
                ->where('t.is_deleted', 0);

            // Apply Ticket Type Filter if provided
            if (!is_null($ticketType)) {
                $query->where('t.type', $ticketType);
            }

            $newTickets = $query->select(
                    't.id as ticket_id', 
                    't.name as ticket_name', 
                    DB::raw("CONCAT(u.firstname, ' ', u.realname) as requester_name")
                ) 
                ->get();

            if ($newTickets->isEmpty()) {
                $this->line("   🟡 No tickets found.");
                return;
            }

            $count = 0;
            foreach ($newTickets as $ticket) {
                // Calculate the "Local" ID we will store
                $localTicketId = $ticket->ticket_id + $idOffset;

                // Check if this specific ID exists locally
                if (in_array($localTicketId, $existingTicketIds)) {
                     // Check if it's soft deleted
                     $po = PurchaseOrder::withTrashed()->where('glpi_ticket_id', $localTicketId)->first();
                     if ($po && $po->trashed()) {
                         $po->restore();
                         $po->status = 'pending';
                         $po->save();
                         $this->line("   -> ♻️  Restored PO for Ticket #<fg=cyan>{$ticket->ticket_id}</> (Local: {$localTicketId})");
                     }
                     continue; 
                }

                $requesterFullName = trim($ticket->requester_name) ?: 'N/A';

                PurchaseOrder::create([
                    'glpi_ticket_id'      => $localTicketId,
                    'type'                => 'job_order_glpi',
                    'notes'               => "GLPI " . strtoupper(str_replace('glpi_', '', $connectionName)) . " Ticket #{$ticket->ticket_id}: {$ticket->ticket_name}",
                    'status'              => 'pending',
                    'glpi_requester_name' => $requesterFullName,
                    'ordered_by_user_id'  => $defaultRequesterId
                ]);

                $this->line("   -> ✨ Created PO for Ticket #<fg=cyan>{$ticket->ticket_id}</> (Local: {$localTicketId})");
                $count++;
            }
            
            $this->info("   ✅ Processed {$count} new tickets.");

        } catch (\Exception $e) {
            $this->error("   ❌ Error syncing from {$connectionName}: " . $e->getMessage());
            Log::error("GLPI Sync ({$connectionName}) Failed: " . $e->getMessage());
        }
    }

    private function cleanupResolvedTickets($connectionName, $idOffset)
    {
        $this->line("--- Cleaning up resolved tickets from <fg=yellow>{$connectionName}</> (Offset: {$idOffset}) ---");

        // 1. Get all pending local POs that belong to this connection/range
        $query = PurchaseOrder::whereNotNull('glpi_ticket_id')
            ->where('status', 'pending'); // Only clean up if they are still pending locally

        if ($idOffset == 0) {
            // IT Tickets (ID < 1,000,000)
            $query->where('glpi_ticket_id', '<', 1000000);
        } else {
            // EN Tickets (ID >= 1,000,000)
            $query->where('glpi_ticket_id', '>=', $idOffset);
        }

        $pendingLocalPos = $query->get();

        if ($pendingLocalPos->isEmpty()) {
             return;
        }

        $localIdMap = $pendingLocalPos->pluck('id', 'glpi_ticket_id')->toArray(); // Map: glpi_ticket_id => local_po_id
        $remoteIdsToCheck = array_keys($localIdMap);
        
        // Adjust for remote query (remove offset)
        $remoteIdsToCheck = array_map(function($id) use ($idOffset) {
            return $id - $idOffset;
        }, $remoteIdsToCheck);

        if (empty($remoteIdsToCheck)) return;

        try {
            // 2. Query Remote DB
            // We want tickets that are Solved (5), Closed (6), or Deleted (is_deleted=1)
            $resolvedTickets = DB::connection($connectionName)
                ->table('glpi_tickets')
                ->whereIn('id', $remoteIdsToCheck)
                ->where(function($q) {
                    $q->whereIn('status', [5, 6]) // Solved or Closed
                      ->orWhere('is_deleted', 1);
                })
                ->pluck('id') // Get ID of tickets that SHOULD be removed
                ->toArray();

            $count = 0;
            foreach ($resolvedTickets as $remoteId) {
                $localGlpiId = $remoteId + $idOffset;
                if (isset($localIdMap[$localGlpiId])) {
                     $poId = $localIdMap[$localGlpiId];
                     $po = PurchaseOrder::find($poId);
                     if ($po) {
                         $po->delete(); // Soft Delete
                         $this->line("   -> 🗑️  Auto-closed PO #{$po->id} (Ticket #{$remoteId} is solved/closed/deleted)");
                         $count++;
                     }
                }
            }
            
            if ($count > 0) {
                $this->info("   ✅ Cleaned up {$count} resolved tickets.");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error cleaning up from {$connectionName}: " . $e->getMessage());
            Log::error("GLPI Cleanup ({$connectionName}) Failed: " . $e->getMessage());
        }
    }
}
