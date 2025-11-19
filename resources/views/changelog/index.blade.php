@extends('layouts.app')

@section('header', 'ประวัติการอัปเดต')
@section('subtitle', 'ติดตามฟีเจอร์ใหม่, การแก้ไข และการปรับปรุงระบบ')

@push('styles')
<style>
    /* ... (Style เดิมของ Timeline) ... */
    .timeline-item .timeline-badge {
        position: absolute;
        left: -10px;
        top: 16px;
        height: 20px;
        width: 20px;
        border-radius: 50%;
        border: 4px solid #fff;
    }
    .timeline-item .timeline-badge-feature { background-color: #4f46e5; } /* Indigo */
    .timeline-item .timeline-badge-bugfix { background-color: #db2777; } /* Pink */
    .timeline-item .timeline-badge-improvement { background-color: #10b981; } /* Emerald */
    
    .timeline-panel {
        position: relative;
        width: 90%;
        margin-left: 10%;
        padding-bottom: 2rem;
    }

    .timeline-line {
        position: absolute;
        left: 0;
        top: 26px;
        width: 4px;
        height: calc(100% - 26px);
        background: #e5e7eb; /* gray-200 */
    }
    
    .timeline-group:last-child .timeline-item:last-child .timeline-line {
        display: none;
    }
</style>
@endpush

@section('content')
<div class="page animate-slide-up-soft">

    {{-- ✅ 1. เพิ่มปุ่ม "เพิ่มรายการ" (เฉพาะ Admin) ✅ --}}
    @can('permission:manage')
    <div class="mb-6 text-right">
        <button type="button" onclick="showModal('add-changelog-modal')" 
                class="inline-flex items-center px-4 py-2 font-medium text-white bg-indigo-600 border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <i class="mr-2 fas fa-plus"></i> เพิ่มรายการอัปเดต
        </button>
    </div>
    @endcan
    
    {{-- (โค้ด Timeline เดิม) --}}
    @forelse ($groupedLogs as $monthYear => $logs)
        <div class="mb-8 timeline-group">
            <h2 class="mb-4 text-2xl font-bold text-gray-700 dark:text-gray-200">{{ $monthYear }}</h2>
            <div class="relative">
                @foreach ($logs as $log)
                    @php
                        $typeInfo = [
                            'feature' => [
                                'text' => 'ฟีเจอร์ใหม่', 'badge_class' => 'timeline-badge-feature',
                                'text_class' => 'text-indigo-600 dark:text-indigo-400',
                            ],
                            'bugfix' => [
                                'text' => 'แก้ไข Bug', 'badge_class' => 'timeline-badge-bugfix',
                                'text_class' => 'text-pink-600 dark:text-pink-400',
                            ],
                            'improvement' => [
                                'text' => 'ปรับปรุง', 'badge_class' => 'timeline-badge-improvement',
                                'text_class' => 'text-emerald-600 dark:text-emerald-400',
                            ],
                        ][$log->type] ?? ['text' => $log->type, 'badge_class' => 'bg-gray-400', 'text_class' => 'text-gray-500'];
                    @endphp

                    <div class="relative timeline-item">
                        <div class="timeline-line"></div>
                        <div class="timeline-badge {{ $typeInfo['badge_class'] }}"></div>
                        <div class="timeline-panel">
                            <div class="p-5 soft-card rounded-2xl gentle-shadow">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center mb-2">
                                    <span class="font-semibold text-gray-600 dark:text-gray-300">
                                        {{ $log->change_date->format('d F Y') }}
                                        @if($log->version)
                                            <span class="ml-2 px-2 py-0.5 text-xs font-mono text-indigo-700 bg-indigo-100 rounded-full dark:text-indigo-200 dark:bg-indigo-900">{{ $log->version }}</span>
                                        @endif
                                    </span>
                                    <span class="font-bold {{ $typeInfo['text_class'] }}">
                                        {{ $typeInfo['text'] }}
                                    </span>
                                </div>
                                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-2">
                                    {{ $log->title }}
                                </h3>
                                <p class="text-gray-700 dark:text-gray-300 mb-4">
                                    {!! nl2br(e($log->description)) !!}
                                </p>
                                @if (!empty($log->files_modified))
                                    <div>
                                        <h4 class="font-semibold text-sm text-gray-500 dark:text-gray-400 mb-1">ไฟล์ที่เกี่ยวข้อง:</h4>
                                        <ul class="list-disc list-inside pl-1">
                                            @foreach ($log->files_modified as $file)
                                                <li class="text-xs text-gray-600 dark:text-gray-300 font-mono">
                                                    {{ $file }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        {{-- ‼️ แก้ไข: ใช้ $changelogs->isEmpty() เพื่อเช็คค่าว่างทั้งหมด (จาก Controller) --}}
        @if($changelogs->isEmpty())
        <div class="p-8 text-center text-gray-500 dark:text-gray-400 soft-card rounded-2xl gentle-shadow">
            <p>ยังไม่มีประวัติการอัปเดต</p>
        </div>
        @endif
    @endforelse
</div>

{{-- ✅ 2. เพิ่ม Modal Form (เฉพาะ Admin) ✅ --}}
@can('permission:manage')
<div class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-black bg-opacity-75" id="add-changelog-modal">
    <div class="w-full max-w-2xl p-6 mx-4 bg-white rounded-2xl soft-card animate-slide-up-soft dark:bg-gray-800">
        
        <form id="changelog-form" action="{{ route('changelog.store') }}" method="POST">
            @csrf
            <div class="flex items-start justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xl font-bold dark:text-gray-100">เพิ่มประวัติการอัปเดต</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" onclick="closeModal('add-changelog-modal')">
                     <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="py-5 space-y-4 max-h-[70vh] overflow-y-auto">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="type" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">ประเภท (Type) <span class="text-red-500">*</span></label>
                        <select id="type" name="type" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                            <option value="">-- กรุณาเลือก --</option>
                            <option value="feature">ฟีเจอร์ใหม่ (Feature)</option>
                            <option value="bugfix">แก้ไข Bug (Bugfix)</option>
                            <option value="improvement">ปรับปรุง (Improvement)</option>
                        </select>
                    </div>
                    <div>
                        <label for="version" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">เวอร์ชัน (Version)</label>
                        <input type="text" id="version" name="version" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" placeholder="เช่น v1.1.2">
                    </div>
                </div>

                <div>
                    <label for="title" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">หัวข้อ (Title) <span class="text-red-500">*</span></label>
                    <input type="text" id="title" name="title" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" placeholder="เช่น ปรับปรุงปุ่มหน้าเบิกอุปกรณ์" required>
                </div>

                <div>
                    <label for="description" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">รายละเอียด (Description) <span class="text-red-500">*</span></label>
                    <textarea id="description" name="description" rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" placeholder="อธิบายว่าทำอะไรไปบ้าง..." required></textarea>
                </div>

                <div>
                    <label for="files_modified_text" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">ไฟล์ที่แก้ไข</label>
                    <textarea id="files_modified_text" name="files_modified_text" rows="4" class="w-full px-3 py-2 font-mono text-sm border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" placeholder="ใส่ 1 ไฟล์ ต่อ 1 บรรทัด (เช่น web.php)"></textarea>
                </div>

            </div>

            <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700 space-x-3">
                 <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500" onclick="closeModal('add-changelog-modal')">ยกเลิก</button>
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="mr-1 -ml-1 fas fa-save"></i> บันทึก
                </button>
            </div>
        </form>
         
    </div>
</div>
@endcan

@endsection

@push('scripts')
{{-- ✅ 3. เพิ่ม Scripts (ถ้ายังไม่มี) ✅ --}}
<script>
    // (ฟังก์ชันเหล่านี้อาจจะมีอยู่แล้วใน app.js แต่ใส่ไว้กันเหนียว)
    function showModal(modalId) {
        const modal = document.getElementById(modalId);
        if(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }
</script>
@endpush