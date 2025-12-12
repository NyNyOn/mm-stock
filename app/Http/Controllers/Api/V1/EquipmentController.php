<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    /**
     * Display a listing of the resource.
     * (ดึงรายการอุปกรณ์ทั้งหมด)
     */
    public function index()
    {
        // ✅ เพิ่ม 'latestImage' เพื่อดึงข้อมูลรูปภาพที่เก็บใน NAS มาเตรียมไว้
        $equipments = Equipment::with(['unit', 'latestImage'])->get();

        // ส่งข้อมูลให้ Resource จัดการแปลงเป็น JSON collection
        return EquipmentResource::collection($equipments);
    }

    /**
     * Display the specified resource.
     * (แสดงรายละเอียดอุปกรณ์ทีละชิ้น ตาม ID)
     */
    public function show(string $id)
    {
        // ✅ เพิ่ม 'latestImage' เช่นกัน และใช้ findOrFail เพื่อดึงข้อมูล
        // ถ้าไม่เจอ ID นี้ ระบบจะส่ง Error 404 กลับไปให้อัตโนมัติ
        $equipment = Equipment::with(['unit', 'latestImage'])->findOrFail($id);

        // ส่งข้อมูลกลับผ่าน Resource แบบชิ้นเดียว
        return new EquipmentResource($equipment);
    }

    // ฟังก์ชันอื่นๆ ไว้ขยายต่อในอนาคต (ตอนนี้ปล่อยว่างไว้ก่อน)
    public function store(Request $request) 
    { 
        // ไว้สำหรับสร้างอุปกรณ์ใหม่ผ่าน API
    }

    public function update(Request $request, string $id) 
    { 
        // ไว้สำหรับแก้ไขอุปกรณ์ผ่าน API
    }

    public function destroy(string $id) 
    { 
        // ไว้สำหรับลบอุปกรณ์ผ่าน API
    }
}