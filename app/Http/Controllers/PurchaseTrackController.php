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

        if (request()->ajax()) {
            return view('purchase-track.partials._list', compact('purchaseOrders'))->render();
        }

        return view('purchase-track.index', compact('purchaseOrders'));
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

        if (request()->ajax()) {
            return view('purchase-track.partials._list', compact('purchaseOrders'))->render();
        }

        return view('purchase-track.rejected', compact('purchaseOrders'));
    }

    private function fetchOrders($statuses)
    {
        $rejectedStatuses = ['cancelled', 'rejected', 'returned', 'inspection_failed'];

        return PurchaseOrder::with([
            'items' => function ($q) use ($rejectedStatuses) {
                // ✅ FILTER: Show ONLY Active (Non-Rejected) items in this view
                $q->whereNotIn('status', $rejectedStatuses)
                  ->with(['equipment' => function ($eq) {
                    $eq->withTrashed()->with(['category', 'unit', 'images']);
                }]);
            },
            'requester',   // ผู้ขอซื้อ
            'orderedBy'    // ผู้กดสั่ง (Admin/System)
        ])
        ->whereIn('status', $statuses)
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