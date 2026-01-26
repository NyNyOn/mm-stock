@extends('layouts.app')

@section('header', 'API Management')
@section('subtitle', 'จัดการการเชื่อมต่อและ API Token')

@section('content')
<div class="container p-4 mx-auto space-y-6">

    @if (session('success'))
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-200 dark:text-green-800" role="alert">
            <span class="font-medium">สำเร็จ!</span> {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
            <span class="font-medium">ผิดพลาด!</span> {{ session('error') }}
        </div>
    @endif

    {{-- ✅✅✅ ส่วนที่ 1: ตั้งค่า PU Hub API Connection ✅✅✅ --}}
    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-4 border-b pb-2">
            <div>
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-network-wired mr-2 text-blue-600"></i> ตั้งค่าการเชื่อมต่อ PU Hub System
                </h3>
                <p class="text-sm text-gray-500 mt-1">กำหนดค่า Endpoint และ Token สำหรับส่งข้อมูลใบขอซื้อและผลการตรวจสอบ</p>
            </div>
            <span class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-100 rounded-full">System Config</span>
        </div>

        <form action="{{ route('management.tokens.updatePuSettings') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="pu_api_enabled" value="1">
            
            {{-- Group: Connection Info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Base URL --}}
                <div class="col-span-1 md:col-span-2">
                    <label for="pu_api_base_url" class="block mb-2 text-sm font-medium text-gray-900">API Base URL</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 text-sm text-gray-900 bg-gray-200 border border-r-0 border-gray-300 rounded-l-md">
                            <i class="fas fa-globe"></i>
                        </span>
                        <input type="url" id="pu_api_base_url" name="pu_api_base_url" 
                               value="{{ old('pu_api_base_url', $puSettings['base_url']) }}"
                               class="rounded-none rounded-r-lg bg-gray-50 border border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full min-w-0 text-sm p-2.5" 
                               placeholder="http://192.168.x.x/api/v1" required>
                    </div>
                </div>

                {{-- API Token --}}
                <div class="col-span-1 md:col-span-2">
                    <label for="pu_api_token" class="block mb-2 text-sm font-medium text-gray-900">API Token (Bearer Token)</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 text-sm text-gray-900 bg-gray-200 border border-r-0 border-gray-300 rounded-l-md">
                            <i class="fas fa-key"></i>
                        </span>
                        <input type="password" id="pu_api_token" name="pu_api_token" 
                               value="{{ old('pu_api_token', $puSettings['token']) }}"
                               class="rounded-none rounded-r-lg bg-gray-50 border border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full min-w-0 text-sm p-2.5 font-mono" 
                               placeholder="วาง Token ที่ได้จากระบบ PU ที่นี่" required>
                    </div>
                </div>

                {{-- Webhook Secret --}}
                <div class="col-span-1 md:col-span-2">
                    <label for="pu_api_webhook_secret" class="block mb-2 text-sm font-medium text-gray-900">Webhook Secret (X-Hub-Secret)</label>
                    <div class="flex">
                        <span class="inline-flex items-center px-3 text-sm text-gray-900 bg-gray-200 border border-r-0 border-gray-300 rounded-l-md">
                            <i class="fas fa-user-secret"></i>
                        </span>
                        <input type="text" id="pu_api_webhook_secret" name="pu_api_webhook_secret" 
                               value="{{ old('pu_api_webhook_secret', $puSettings['webhook_secret']) }}"
                               class="rounded-none rounded-r-lg bg-gray-50 border border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500 block w-full min-w-0 text-sm p-2.5 font-mono" 
                               placeholder="กำหนด Secret Key สำหรับตรวจสอบ Webhook">
                    </div>
                </div>

                {{-- PR Intake Path --}}
                <div>
                    <label for="pu_api_intake_path" class="block mb-2 text-sm font-medium text-gray-900">PR Intake Path (สร้าง PR)</label>
                    <input type="text" id="pu_api_intake_path" name="pu_api_intake_path" 
                           value="{{ old('pu_api_intake_path', $puSettings['intake_path']) }}"
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" 
                           placeholder="/intake/pr" required>
                </div>

                {{-- Arrival Notification Path --}}
                <div>
                    <label for="pu_api_arrival_path" class="block mb-2 text-sm font-medium text-gray-900">Arrival Notification Path (แจ้งของเข้า)</label>
                    <input type="text" id="pu_api_arrival_path" name="pu_api_arrival_path" 
                           value="{{ old('pu_api_arrival_path', $puSettings['arrival_path']) }}"
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" 
                           placeholder="/api/v1/notify-hub-arrival">
                </div>

                {{-- Inspection Path --}}
                <div>
                    <label for="pu_api_inspection_path" class="block mb-2 text-sm font-medium text-gray-900">Inspection Path</label>
                    <input type="text" id="pu_api_inspection_path" name="pu_api_inspection_path" 
                           value="{{ old('pu_api_inspection_path', $puSettings['inspection_path']) }}"
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" 
                           placeholder="/inspections" required>
                </div>

                {{-- Origin Department ID --}}
                <div class="col-span-1 md:col-span-2">
                    <label for="pu_api_origin_department_id" class="block mb-2 text-sm font-medium text-gray-900">
                        Default Origin Department ID (Fallback)
                    </label>
                    <div class="flex items-center">
                        <input type="number" id="pu_api_origin_department_id" name="pu_api_origin_department_id" 
                               value="{{ old('pu_api_origin_department_id', $puSettings['origin_department_id']) }}"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" 
                               placeholder="เช่น 99">
                        <div class="ml-2 text-sm text-gray-500">
                            <i class="fas fa-info-circle text-blue-500"></i> ใช้เมื่อระบบหา ID จาก User ไม่เจอ
                        </div>
                    </div>
                </div>
            </div>

            <hr class="border-gray-200">

            {{-- Group: Priority Mapping --}}
            <div>
                <h4 class="text-md font-semibold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-tasks mr-2 text-orange-500"></i> Priority Mapping (การจับคู่ระดับความสำคัญ)
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Scheduled --}}
                    <div>
                        <label for="pu_api_priority_scheduled" class="block mb-2 text-sm font-medium text-gray-700">Scheduled (ตามรอบ)</label>
                        <input type="text" id="pu_api_priority_scheduled" name="pu_api_priority_scheduled" 
                               value="{{ old('pu_api_priority_scheduled', $puSettings['priority_scheduled']) }}"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-orange-500 focus:border-orange-500 block w-full p-2.5" 
                               placeholder="ค่า Default: Scheduled" required>
                    </div>

                    {{-- Urgent --}}
                    <div>
                        <label for="pu_api_priority_urgent" class="block mb-2 text-sm font-medium text-gray-700">Urgent (ด่วน)</label>
                        <input type="text" id="pu_api_priority_urgent" name="pu_api_priority_urgent" 
                               value="{{ old('pu_api_priority_urgent', $puSettings['priority_urgent']) }}"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5" 
                               placeholder="ค่า Default: Urgent" required>
                    </div>

                    {{-- Job --}}
                    <div>
                        <label for="pu_api_priority_job" class="block mb-2 text-sm font-medium text-gray-700">Job Order (ตามงาน)</label>
                        <input type="text" id="pu_api_priority_job" name="pu_api_priority_job" 
                               value="{{ old('pu_api_priority_job', $puSettings['priority_job']) }}"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full p-2.5" 
                               placeholder="ค่า Default: Job" required>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">กำหนดชื่อ Priority ให้ตรงกับที่ระบบ PU ต้องการ (Case Sensitive)</p>
            </div>

            <div class="flex justify-end mt-4 pt-4 border-t">
                <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> บันทึกการตั้งค่า
                </button>
            </div>
        </form>
    </div>

    {{-- ✅✅✅ ส่วนที่ 2: สร้าง API Token (Generate Tokens) ✅✅✅ --}}
    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-key mr-2 text-green-600"></i> สร้าง API Token ใหม่ (สำหรับให้ระบบอื่นเรียกหาเรา)
        </h3>
        
        <form action="{{ route('management.tokens.store') }}" method="POST" class="flex gap-4 items-end">
            @csrf
            <div class="flex-grow">
                <label for="token_name" class="block mb-2 text-sm font-medium text-gray-900">ชื่อ Token (ระบุชื่อระบบที่นำไปใช้)</label>
                <input type="text" id="token_name" name="token_name" 
                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" 
                       placeholder="เช่น iPad-Scan-Station-1" required>
            </div>
            <button type="submit" class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> สร้าง Token
            </button>
        </form>

        @if (session('newToken'))
            <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h4 class="text-yellow-800 font-bold flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i> โปรดคัดลอก Token นี้เก็บไว้ทันที!
                </h4>
                <p class="text-sm text-yellow-700 mb-2">Token นี้จะแสดงเพียงครั้งเดียวเท่านั้น คุณจะไม่สามารถดูได้อีก</p>
                <div class="relative">
                    <input type="text" value="{{ session('newToken') }}" readonly 
                           class="w-full bg-white border border-gray-300 text-gray-900 text-sm rounded-lg p-2.5 font-mono"
                           onclick="this.select()">
                </div>
            </div>
        @endif
    </div>

    {{-- ✅✅✅ ส่วนที่ 3: รายการ Token ที่ใช้งานอยู่ ✅✅✅ --}}
    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
        <h3 class="text-lg font-bold text-gray-800 mb-4">รายการ Active Tokens</h3>
        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3">ชื่อ Token</th>
                        <th scope="col" class="px-6 py-3">สร้างเมื่อ</th>
                        <th scope="col" class="px-6 py-3">ใช้งานล่าสุด</th>
                        <th scope="col" class="px-6 py-3 text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tokens as $token)
                        <tr class="bg-white border-b hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                {{ $token->name }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $token->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'ยังไม่เคยใช้งาน' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('management.tokens.destroy', $token->id) }}" method="POST" 
                                      onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบ Token นี้? ระบบที่ใช้ Token นี้จะไม่สามารถเชื่อมต่อได้อีก');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:underline">
                                        <i class="fas fa-trash-alt"></i> ลบ
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                ยังไม่มีรายการ Token
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection