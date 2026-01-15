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
            'completed'
        ];

        $purchaseOrders = $this->fetchOrders($trackingStatuses);

        if (request()->ajax()) {
            return view('purchase-track.partials._list', compact('purchaseOrders'))->render();
        }

        return view('purchase-track.index', compact('purchaseOrders'));
    }

    public function rejectedIndex()
    {
        $trackingStatuses = ['cancelled', 'rejected'];

        $purchaseOrders = $this->fetchOrders($trackingStatuses);

        if (request()->ajax()) {
            return view('purchase-track.partials._list', compact('purchaseOrders'))->render();
        }

        return view('purchase-track.rejected', compact('purchaseOrders'));
    }

    private function fetchOrders($statuses)
    {
        return PurchaseOrder::with([
            'items' => function ($q) {
                // โหลดข้อมูลอุปกรณ์ (รวมที่ถูกลบไปแล้ว) + Unit + Images + Category
                $q->with(['equipment' => function ($eq) {
                    $eq->withTrashed()->with(['category', 'unit', 'images']);
                }]);
            },
            'requester',   // ผู้ขอซื้อ
            'orderedBy'    // ผู้กดสั่ง (Admin/System)
        ])
        ->whereIn('status', $statuses)
        ->orderBy('updated_at', 'desc')
        ->orderBy('id', 'desc')
        ->paginate(10);
    }
}