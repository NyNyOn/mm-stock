<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PurchaseTrackController extends Controller
{
    /**
     * Display the purchase order tracking page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // ในอนาคต เราจะใส่ Logic สำหรับดึงข้อมูลสถานะใบสั่งซื้อที่นี่
        return view('purchase-track.index');
    }
}
