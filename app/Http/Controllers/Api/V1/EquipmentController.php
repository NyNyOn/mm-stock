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
     */
    public function index()
    {
        // ดึงข้อมูล Equipment ทั้งหมดพร้อมกับ relationship 'unit' ที่จำเป็น
        $equipments = Equipment::with('unit')->get();

        // ส่งข้อมูลให้ Resource จัดการแปลงเป็น JSON collection
        return EquipmentResource::collection($equipments);
    }

    // ฟังก์ชันอื่นๆ ยังไม่ได้ใช้งาน
    public function store(Request $request) { }
    public function show(string $id) { }
    public function update(Request $request, string $id) { }
    public function destroy(string $id) { }
}