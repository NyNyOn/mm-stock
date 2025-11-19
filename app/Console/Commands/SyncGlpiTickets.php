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
        
        try {
            $existingTicketIds = PurchaseOrder::whereNotNull('glpi_ticket_id')->pluck('glpi_ticket_id');
            $this->line("🔍 Found " . $existingTicketIds->count() . " existing POs linked to GLPI tickets. They will be ignored.");

            $newTickets = DB::connection('glpi')
                ->table('glpi_tickets as t')
                ->join('glpi_tickets_users as tu', 't.id', '=', 'tu.tickets_id')
                ->join('glpi_users as u', 'tu.users_id', '=', 'u.id')
                ->where('t.status', 4)
                ->where('tu.type', 1)
                ->where('t.is_deleted', 0)
                ->whereNotIn('t.id', $existingTicketIds)
                ->select(
                    't.id as ticket_id', 
                    't.name as ticket_name', 
                    DB::raw("CONCAT(u.firstname, ' ', u.realname) as requester_name")
                ) 
                ->get();

            if ($newTickets->isEmpty()) {
                $this->warn('🟡 No new tickets with status 4 and a requester found in GLPI.');
                return 0;
            }

            $this->info("✅ Found <fg=green>{$newTickets->count()}</> new tickets to process.");

            foreach ($newTickets as $ticket) {
                $requesterFullName = trim($ticket->requester_name) ?: 'N/A';

                $purchaseOrder = PurchaseOrder::firstOrCreate(
                    ['glpi_ticket_id' => $ticket->ticket_id, 'type' => 'job_order_glpi'],
                    [
                        'notes' => "GLPI Ticket #{$ticket->ticket_id}: {$ticket->ticket_name}",
                        'status' => 'pending',
                        'glpi_requester_name' => $requesterFullName,
                        'ordered_by_user_id' => $jobRequesterId // Assign the requester ID here
                    ]
                );

                if ($purchaseOrder->wasRecentlyCreated) {
                    $this->line("   -> Created PO for Ticket #<fg=cyan>{$ticket->ticket_id}</> (Requester: <fg=yellow>{$requesterFullName}</>)");
                }
            }

            $this->info('✨ Synchronization complete.');

        } catch (\Exception $e) {
            $this->error('❌ An error occurred during GLPI sync!');
            $this->error('   Error Message: ' . $e->getMessage());
            Log::error('GLPI Sync Failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
