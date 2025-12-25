<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\PersonalAccessToken; // ✅ เพิ่มการเรียกใช้ Model นี้
use Illuminate\Support\Facades\Log;
use Throwable;

class ApiTokenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // ✅ แก้ไข: ดึง Token ทั้งหมดในระบบ (แทนที่จะดึงเฉพาะของ User ปัจจุบัน)
        // เพื่อให้ Admin เห็น Token เก่าที่อาจจะสร้างโดย User อื่น หรือสร้างผ่าน Seeder
        $tokens = PersonalAccessToken::orderBy('created_at', 'desc')->get();

        // ดึงค่า Config ปัจจุบัน
        $puSettings = [
            'enabled'              => Setting::where('key', 'pu_api_enabled')->value('value') ?? config('services.pu_hub.enabled', true),
            'base_url'             => Setting::where('key', 'pu_api_base_url')->value('value') ?? config('services.pu_hub.base_url'),
            'token'                => Setting::where('key', 'pu_api_token')->value('value') ?? config('services.pu_hub.token'),
            'intake_path'          => Setting::where('key', 'pu_api_intake_path')->value('value') ?? config('services.pu_hub.intake_path'),
            'inspection_path'      => Setting::where('key', 'pu_api_inspection_path')->value('value') ?? config('services.pu_hub.inspection_path'),
            'origin_department_id' => Setting::where('key', 'pu_api_origin_department_id')->value('value') ?? config('services.pu_hub.origin_department_id'),
            
            // Priority Mapping
            'priority_scheduled'   => Setting::where('key', 'pu_api_priority_scheduled')->value('value') ?? config('services.pu_hub.priorities.scheduled', 'Scheduled'),
            'priority_urgent'      => Setting::where('key', 'pu_api_priority_urgent')->value('value') ?? config('services.pu_hub.priorities.urgent', 'Urgent'),
            'priority_job'         => Setting::where('key', 'pu_api_priority_job')->value('value') ?? config('services.pu_hub.priorities.job', 'Job'),
        ];

        return view('management.tokens.index', [
            'tokens' => $tokens,
            'puSettings' => $puSettings,
        ]);
    }

    /**
     * Store a new API token.
     */
    public function store(Request $request)
    {
        $request->validate([
            'token_name' => 'required|string|max:255',
        ]);

        try {
            $token = $request->user()->createToken($request->token_name);

            return redirect()->route('management.tokens.index')
                ->with('success', 'สร้าง Token สำเร็จ')
                ->with('newToken', $token->plainTextToken);
        } catch (Throwable $e) {
            Log::error('Error creating token: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการสร้าง Token');
        }
    }

    public function show($tokenId)
    {
        return redirect()->route('management.tokens.index');
    }

    public function destroy(Request $request, $tokenId)
    {
        try {
            // ✅ แก้ไข: ลบ Token โดยค้นหาจาก ID โดยตรง (ไม่ผ่าน user()->tokens())
            // เพื่อให้ Admin สามารถลบ Token เก่าๆ ของระบบได้
            $token = PersonalAccessToken::find($tokenId);
            
            if ($token) {
                $token->delete();
                return redirect()->route('management.tokens.index')->with('success', 'ลบ Token เรียบร้อยแล้ว');
            }
            
            return back()->with('error', 'ไม่พบ Token ที่ต้องการลบ');

        } catch (Throwable $e) {
            Log::error('Error deleting token: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการลบ Token');
        }
    }

    /**
     * Update PU Hub API Settings.
     */
    public function updatePuSettings(Request $request)
    {
        $request->validate([
            'pu_api_enabled' => 'required|boolean', 
            'pu_api_base_url' => 'required|url',
            'pu_api_token' => 'required|string',
            'pu_api_intake_path' => 'required|string',
            'pu_api_inspection_path' => 'required|string',
            'pu_api_origin_department_id' => 'nullable|integer',
            // Priority Validations
            'pu_api_priority_scheduled' => 'required|string',
            'pu_api_priority_urgent' => 'required|string',
            'pu_api_priority_job' => 'required|string',
        ]);

        try {
            $settings = [
                'pu_api_enabled'              => $request->pu_api_enabled, 
                'pu_api_base_url'             => $request->pu_api_base_url,
                'pu_api_token'                => $request->pu_api_token,
                'pu_api_intake_path'          => $request->pu_api_intake_path,
                'pu_api_inspection_path'      => $request->pu_api_inspection_path,
                'pu_api_origin_department_id' => $request->pu_api_origin_department_id,
                // Priority Settings
                'pu_api_priority_scheduled'   => $request->pu_api_priority_scheduled,
                'pu_api_priority_urgent'      => $request->pu_api_priority_urgent,
                'pu_api_priority_job'         => $request->pu_api_priority_job,
            ];

            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }

            return redirect()->route('management.tokens.index')->with('success', 'บันทึกการตั้งค่า PU Hub API เรียบร้อยแล้ว');

        } catch (Throwable $e) {
            Log::error('Error updating PU settings: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการบันทึกการตั้งค่า: ' . $e->getMessage());
        }
    }
}