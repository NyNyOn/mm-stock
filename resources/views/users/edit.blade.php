@extends('layouts.app')

{{-- ตั้งค่า Header ของหน้า --}}
@section('header', 'แก้ไขสิทธิ์ผู้ใช้งาน')
@section('subtitle', 'เปลี่ยนกลุ่ม Role สำหรับผู้ใช้ในระบบ')

{{-- เนื้อหาหลักของหน้า --}}
@section('content')
<div class="flex justify-center">
    <div class="w-full max-w-2xl">
        <form method="POST" action="{{ route('users.update', $user->id) }}">
            @csrf
            @method('PUT')

            <div class="p-8 space-y-8 soft-card">

                {{-- ส่วนแสดงข้อมูลผู้ใช้ที่กำลังแก้ไข --}}
                <div>
                    <h3 class="text-lg font-bold text-gray-800">ข้อมูลผู้ใช้</h3>
                    <div class="p-4 mt-4 space-y-2 border rounded-2xl bg-gray-50/50">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Name:</span>
                            <span class="font-semibold text-gray-700">{{ $user->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Username:</span>
                            <span class="font-semibold text-gray-700">{{ $user->username }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Current Group:</span>
                            <span class="font-semibold text-purple-600">{{ $user->serviceUserRole->userGroup->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                {{-- ส่วนฟอร์มสำหรับเลือกสิทธิ์ --}}
                <div>
                    <label for="user_group_id" class="block mb-2 text-lg font-bold text-gray-800">
                        กำหนดสิทธิ์ใหม่ <span class="text-red-500">*</span>
                    </label>

                    <select id="user_group_id" name="user_group_id" class="w-full px-4 py-3 font-medium border-0 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 text-md gentle-shadow">
                        <option value="">-- กรุณาเลือกสิทธิ์ --</option>
                        @foreach($groups as $group)
                            {{-- ตรวจสอบว่า group ไหนคือ group ปัจจุบันของ user เพื่อให้แสดงผลเป็น default --}}
                            <option value="{{ $group->id }}" {{ optional($user->serviceUserRole)->group_id == $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>

                    {{-- แสดง Error ถ้ามี --}}
                    @error('user_group_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ส่วนปุ่ม Submit และ Cancel --}}
                <div class="flex items-center justify-end pt-4 space-x-4">
                    <a href="{{ route('users.index') }}" class="px-6 py-3 font-bold text-gray-700 transition-all bg-gray-200 rounded-xl hover:bg-gray-300">
                        ยกเลิก
                    </a>

                    <button type="submit" class="px-8 py-3 font-bold text-white transition-all bg-gradient-to-br from-blue-400 to-purple-500 rounded-xl hover:shadow-lg button-soft gentle-shadow">
                        <i class="mr-2 fas fa-save"></i>
                        บันทึกการเปลี่ยนแปลง
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection
