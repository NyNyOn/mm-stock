<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseTrackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $trackingStatuses = [
            'ordered',
            'approved',
            'shipped_from_supplier',
            'partial_receive',
            'completed',
            'pending' // ✅ Include Pending (Resubmitted POs)
        ];

        $purchaseOrders = $this->fetchOrders($trackingStatuses);

        // ✅ Optimization: Pre-fetch Settings to avoid N+1 in Loop
        $automationRequesterId = \App\Models\Setting::where('key', 'automation_requester_id')->value('value');
        $automationJobRequesterId = \App\Models\Setting::where('key', 'automation_job_requester_id')->value('value');
        
        // Eager Load these users if they exist
        $automationUsers = [];
        if ($automationRequesterId) $automationUsers['scheduled'] = \App\Models\User::find($automationRequesterId);
        if ($automationJobRequesterId) {
            $user = \App\Models\User::find($automationJobRequesterId);
            $automationUsers['job_order'] = $user;
            $automationUsers['job_order_glpi'] = $user;
        }

        // ✅ Optimization: Pre-fetch Equipment Status for Placeholder Logic
        $itemNames = $purchaseOrders->pluck('items')->flatten()->pluck('item_description')->filter()->unique();
        $equipmentStatusMap = \App\Models\Equipment::withTrashed()
            ->whereIn('name', $itemNames)
            ->select('name', 'deleted_at')
            ->get()
            ->groupBy('name')
            ->map(function ($group) {
                if ($group->contains(fn($e) => is_null($e->deleted_at))) return 'active'; // มี Active
                return 'trashed'; // มีแต่ Trashed
            });

        $viewData = compact('purchaseOrders', 'automationUsers', 'equipmentStatusMap');

        if (request()->ajax()) {
            return view('purchase-track.partials._list', $viewData)->render();
        }

        return view('purchase-track.index', $viewData);
    }

    public function rejectedIndex()
    {
        // ✅ Updated Query: Include Whole PO Rejection OR Partial Item Rejection
        $rejectedStatuses = ['cancelled', 'rejected', 'returned', 'inspection_failed'];

        $purchaseOrders = PurchaseOrder::with([
            'items' => function ($q) use ($rejectedStatuses) {
                // ✅ FILTER: Show ONLY Cancelled/Rejected/Returned items in this view
                $q->whereIn('status', $rejectedStatuses)
                  ->with(['equipment' => function ($eq) {
                    $eq->withTrashed()->with(['category', 'unit', 'images']);
                }]);
            },
            'requester',
            'orderedBy'
        ])
        ->where(function($query) use ($rejectedStatuses) {
             // 1. PO is explicitly Rejected/Cancelled
             $query->whereIn('status', ['cancelled', 'rejected'])
             // 2. OR PO is Active but has Rejected-like Items
                   ->orWhereHas('items', function($q) use ($rejectedStatuses) {
                       $q->whereIn('status', $rejectedStatuses);
                   });
        })
        ->when(request('search'), function($q, $search) {
             $q->where(function($sub) use ($search) {
                 $sub->where('po_number', 'like', "%{$search}%")
                     ->orWhere('pr_number', 'like', "%{$search}%")
                     ->orWhereHas('items', function($itemQ) use ($search) {
                          $itemQ->where('item_description', 'like', "%{$search}%")
                                ->orWhereHas('equipment', function($eqQ) use ($search) {
                                    $eqQ->where('name', 'like', "%{$search}%");
                                });
                     });
             });
        })
        ->orderBy('updated_at', 'desc')
        ->orderBy('id', 'desc')
        ->paginate(10);

        // ✅ Optimization: Pre-fetch Settings
        $automationRequesterId = \App\Models\Setting::where('key', 'automation_requester_id')->value('value');
        $automationJobRequesterId = \App\Models\Setting::where('key', 'automation_job_requester_id')->value('value');
        
        $automationUsers = [];
        if ($automationRequesterId) $automationUsers['scheduled'] = \App\Models\User::find($automationRequesterId);
        if ($automationJobRequesterId) {
            $user = \App\Models\User::find($automationJobRequesterId);
            $automationUsers['job_order'] = $user;
            $automationUsers['job_order_glpi'] = $user;
        }

        // ✅ Optimization: Pre-fetch Equipment Status for Placeholder Logic
        $itemNames = $purchaseOrders->pluck('items')->flatten()->pluck('item_description')->filter()->unique();
        $equipmentStatusMap = \App\Models\Equipment::withTrashed()
            ->whereIn('name', $itemNames)
            ->select('name', 'deleted_at')
            ->get()
            ->groupBy('name')
            ->map(function ($group) {
                if ($group->contains(fn($e) => is_null($e->deleted_at))) return 'active'; 
                return 'trashed'; 
            });

        $viewData = compact('purchaseOrders', 'automationUsers', 'equipmentStatusMap');

        if (request()->ajax()) {
            return view('purchase-track.partials._list', $viewData)->render();
        }

        return view('purchase-track.rejected', $viewData);
    }

    private function fetchOrders($statuses)
    {
        $rejectedStatuses = ['cancelled', 'rejected', 'returned', 'inspection_failed'];

        return PurchaseOrder::with([
            'items' => function ($q) use ($rejectedStatuses) {
                // ✅ FILTER: Show ONLY Active (Non-Rejected) items in this view
                $q->whereNotIn('status', $rejectedStatuses)
                  ->with(['equipment' => function ($eq) {
                    $eq->withTrashed()->with(['category', 'unit', 'primaryImage', 'latestImage']);
                }]);
            },
            'requester',   // ผู้ขอซื้อ
            'orderedBy'    // ผู้กดสั่ง (Admin/System)
        ])
        ->whereIn('status', $statuses)
        // ✅ FILTER: Hide "Draft" Pending POs (Manual Check) from Tracking
        // Show Pending ONLY if it's a Resubmit or has a PR/PO Number assigned.
        ->where(function($query) {
             $query->where('status', '!=', 'pending')
                   ->orWhereNotNull('po_number')
                   ->orWhereNotNull('pr_number')
                   ->orWhere('pu_data->is_resubmit', true);
        })
        // ✅ FILTER: Ensure we only get POs that actually have active items left
        ->whereHas('items', function($q) use ($rejectedStatuses) {
            $q->whereNotIn('status', $rejectedStatuses);
        })
        ->when(request('search'), function($q, $search) {
             $q->where(function($sub) use ($search) {
                 $sub->where('po_number', 'like', "%{$search}%")
                     ->orWhere('pr_number', 'like', "%{$search}%")
                     ->orWhereHas('items', function($itemQ) use ($search) {
                          $itemQ->where('item_description', 'like', "%{$search}%")
                                ->orWhereHas('equipment', function($eqQ) use ($search) {
                                    $eqQ->where('name', 'like', "%{$search}%");
                                });
                     });
             });
        })
        ->orderBy('updated_at', 'desc')
        ->orderBy('id', 'desc')
        ->paginate(10);
    }
}