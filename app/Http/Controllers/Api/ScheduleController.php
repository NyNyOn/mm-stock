<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    /**
     * รับกำหนดการส่ง PO จาก PU Hub
     * Logic: คำนวณวันส่งล่วงหน้า 1 วันจากวันที่ PU กำหนด
     *
     * POST /api/v1/schedule/sync
     */
    public function sync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deadline_day' => 'required|integer|min:1|max:31',
            'deadline_time' => 'required|date_format:H:i',
        ], [
            'deadline_day.required' => 'กรุณาระบุวันที่กำหนดส่ง',
            'deadline_day.min' => 'วันที่ต้องอยู่ระหว่าง 1-31',
            'deadline_day.max' => 'วันที่ต้องอยู่ระหว่าง 1-31',
            'deadline_time.required' => 'กรุณาระบุเวลากำหนดส่ง',
            'deadline_time.date_format' => 'รูปแบบเวลาต้องเป็น HH:mm',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $deadlineDay = (int) $request->deadline_day;
            $deadlineTime = $request->deadline_time;

            // คำนวณวันส่งล่วงหน้า 1 วัน
            // ถ้า deadline_day = 1 ให้ใช้วันที่ 28 ของเดือนก่อน (เพื่อความปลอดภัย)
            if ($deadlineDay == 1) {
                $autoSendDay = 28; // วันสุดท้ายที่ปลอดภัยของเดือนก่อน
            } else {
                $autoSendDay = $deadlineDay - 1;
            }

            // บันทึกกำหนดการ PU (สำหรับแสดงใน UI)
            Setting::updateOrCreate(
                ['key' => 'pu_deadline_day'],
                ['value' => $deadlineDay]
            );
            Setting::updateOrCreate(
                ['key' => 'pu_deadline_time'],
                ['value' => $deadlineTime]
            );

            // บันทึกวันส่งอัตโนมัติ (คำนวณแล้ว)
            Setting::updateOrCreate(
                ['key' => 'auto_po_schedule_day'],
                ['value' => $autoSendDay]
            );
            Setting::updateOrCreate(
                ['key' => 'auto_po_schedule_time'],
                ['value' => $deadlineTime]
            );

            Log::info('[Schedule Sync] Received from PU Hub', [
                'pu_deadline_day' => $deadlineDay,
                'pu_deadline_time' => $deadlineTime,
                'auto_send_day' => $autoSendDay,
                'auto_send_time' => $deadlineTime,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Schedule synced successfully',
                'data' => [
                    'pu_deadline_day' => $deadlineDay,
                    'pu_deadline_time' => $deadlineTime,
                    'auto_send_day' => $autoSendDay,
                    'auto_send_time' => $deadlineTime,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('[Schedule Sync] Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync schedule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ดึงกำหนดการปัจจุบัน
     * GET /api/v1/schedule
     */
    public function show()
    {
        $puDeadlineDay = Setting::where('key', 'pu_deadline_day')->value('value');
        $puDeadlineTime = Setting::where('key', 'pu_deadline_time')->value('value');
        $autoSendDay = Setting::where('key', 'auto_po_schedule_day')->value('value');
        $autoSendTime = Setting::where('key', 'auto_po_schedule_time')->value('value');

        return response()->json([
            'success' => true,
            'data' => [
                'pu_deadline_day' => $puDeadlineDay,
                'pu_deadline_time' => $puDeadlineTime,
                'auto_send_day' => $autoSendDay,
                'auto_send_time' => $autoSendTime,
            ],
        ]);
    }
}
