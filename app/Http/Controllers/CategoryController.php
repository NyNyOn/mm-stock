<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Equipment; // (สำคัญ) เพิ่มการเรียกใช้ Equipment Model
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->get();
        return view('categories.index', [
            'header' => '📂 จัดการประเภท', 'subtitle' => 'เพิ่ม/ลบ/แก้ไข ประเภทอุปกรณ์',
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'prefix' => 'nullable|string|max:5|unique:categories,prefix',
        ]);
        Category::create($request->all());
        return back()->with('success', 'เพิ่มประเภทสำเร็จแล้ว');
    }

    public function destroy(Category $category)
    {
        if ($category->equipments()->count() > 0) {
            return back()->with('error', 'ไม่สามารถลบได้ เนื่องจากมีอุปกรณ์ผูกอยู่กับประเภทนี้');
        }
        $category->delete();
        return back()->with('success', 'ลบประเภทสำเร็จแล้ว');
    }

    /**
     * (สำคัญ) ฟังก์ชันใหม่สำหรับคำนวณ Serial Number
     */
    public function getNextSerialNumber(Request $request)
    {
        $request->validate(['category_id' => 'required|integer|exists:categories,id']);

        $category = Category::find($request->category_id);

        if (!$category || !$category->prefix) {
            return response()->json(['success' => true, 'serial_number' => '']);
        }

        $prefix = $category->prefix;
        $like_pattern = $prefix . '-%';

        $lastEquipment = Equipment::where('serial_number', 'LIKE', $like_pattern)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastEquipment) {
            $parts = explode('-', $lastEquipment->serial_number);
            $lastNumber = (int)end($parts);
            $nextNumber = $lastNumber + 1;
        }

        $newSerialNumber = $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        // ตรวจสอบเผื่อว่าเลขถูกใช้งานแล้ว (กรณีมีการลบข้อมูล)
        while (Equipment::where('serial_number', $newSerialNumber)->exists()) {
            $nextNumber++;
            $newSerialNumber = $prefix . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        }

        return response()->json(['success' => true, 'serial_number' => $newSerialNumber]);
    }
}