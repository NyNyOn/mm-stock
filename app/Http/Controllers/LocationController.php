<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::orderBy('name')->get();

        // หมายเหตุ: โค้ดส่วนนี้เป็นแบบเก่า หากคุณมีการปรับแก้ View ให้รับ @section('header')
        // ควรใช้ return view('locations.index', compact('locations')); แล้วไปกำหนด header ใน view แทน
        return view('locations.index', [
            'header' => '📍 จัดการสถานที่',
            'subtitle' => 'เพิ่ม/ลบ/แก้ไข สถานที่เก็บอุปกรณ์',
            'locations' => $locations
        ]);
    }

    public function store(Request $request)
    {
        // แก้ไขให้ใช้ $validatedData เพื่อป้องกัน MassAssignmentException
        $validatedData = $request->validate([
            'name' => 'required|string|max:100|unique:locations,name',
            'description' => 'nullable|string',
        ]);

        Location::create($validatedData);

        return back()->with('success', 'เพิ่มสถานที่สำเร็จแล้ว');
    }

    public function destroy(Location $location)
    {
        // แก้ไขให้ตรวจสอบเฉพาะอุปกรณ์ที่ยังไม่ถูก Soft Delete
        if ($location->equipments()->whereNull('deleted_at')->count() > 0) {
            return back()->with('error', 'ไม่สามารถลบได้ เนื่องจากมีอุปกรณ์ผูกอยู่กับสถานที่นี้');
        }
        
        $location->delete();
        
        return back()->with('success', 'ลบสถานที่สำเร็จแล้ว');
    }
}