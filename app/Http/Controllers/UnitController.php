<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::orderBy('name')->get();

        return view('units.index', [
            'header' => '📏 จัดการหน่วยนับ',
            'subtitle' => 'เพิ่ม/ลบ/แก้ไข หน่วยนับสำหรับอุปกรณ์',
            'units' => $units
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:units,name',
        ]);

        Unit::create($request->all());

        return back()->with('success', 'เพิ่มหน่วยนับสำเร็จแล้ว');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->equipments()->count() > 0) {
            return back()->with('error', 'ไม่สามารถลบได้ เนื่องจากมีอุปกรณ์ผูกอยู่กับหน่วยนับนี้');
        }
        $unit->delete();
        return back()->with('success', 'ลบหน่วยนับสำเร็จแล้ว');
    }
}