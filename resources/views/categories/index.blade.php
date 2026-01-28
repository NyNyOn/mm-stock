@extends('layouts.app')

@section('header', '📂 จัดการประเภท')
@section('subtitle', 'เพิ่ม/ลบ/แก้ไข ประเภทอุปกรณ์')

@section('content')
    <div id="categories-page" class="page animate-slide-up-soft">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- ซ้าย: ฟอร์มเพิ่มประเภทแบบคงที่ --}}
            <div class="lg:col-span-1">
                <div class="soft-card rounded-2xl p-6 gentle-shadow sticky top-24">
                    <h3 class="text-xl font-bold gradient-text-soft mb-4">📂 เพิ่มประเภทใหม่</h3>

                    {{-- Flash messages --}}
                    @if (session('success'))
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                            <p>{{ session('success') }}</p>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                            <p>{{ session('error') }}</p>
                        </div>
                    @endif

                    {{-- Validation errors --}}
                    @if ($errors->any())
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Create form (RESTful: POST -> categories.store) --}}
                    <form method="POST" action="{{ route('categories.store') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">ชื่อประเภท *</label>
                                <input
                                    type="text"
                                    name="name"
                                    id="name"
                                    required
                                    value="{{ old('name') }}"
                                    placeholder="เช่น คอมพิวเตอร์, จอภาพ, อุปกรณ์เครือข่าย"
                                    class="mt-1 block w-full px-4 py-3 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 bg-transparent text-gray-700 border-0 text-sm font-medium gentle-shadow"
                                >
                            </div>

                            <div>
                                <label for="prefix" class="block text-sm font-medium text-gray-700">Prefix (สำหรับ S/N)</label>
                                <input
                                    type="text"
                                    name="prefix"
                                    id="prefix"
                                    value="{{ old('prefix') }}"
                                    placeholder="เช่น PC, MON, NET"
                                    class="mt-1 block w-full px-4 py-3 soft-card rounded-xl focus:ring-2 focus:ring-blue-300 bg-transparent text-gray-700 border-0 text-sm font-medium gentle-shadow font-mono"
                                >
                            </div>
                        </div>

                        <div class="mt-6">
                            <button
                                type="submit"
                                class="w-full px-4 py-3 bg-gradient-to-br from-blue-100 to-blue-200 text-blue-700 rounded-xl hover:shadow-lg transition-all button-soft gentle-shadow font-medium"
                            >
                                <i class="fas fa-save mr-2"></i><span>บันทึก</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ขวา: ตารางรายการประเภท --}}
            <div class="lg:col-span-2">
                <div class="soft-card rounded-2xl p-6 gentle-shadow">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">รายการประเภททั้งหมด</h3>

                    <div class="overflow-x-auto scrollbar-soft">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-blue-50 to-purple-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-700 text-sm">ชื่อประเภท</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-700 text-sm">Prefix</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-700 text-sm">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($categories as $category)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-800">
                                            {{ $category->name }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-800">
                                            {{ $category->prefix }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center space-x-2">
                                                {{-- ✅✅✅ START: โค้ดที่แก้ไข ✅✅✅ --}}
                                                {{-- ✅ ปุ่มตั้งค่าแบบประเมิน --}}
                                                <button type="button"
                                                        onclick="openEvalConfig({{ $category->id }}, '{{ addslashes($category->name) }}')"
                                                        class="p-2 bg-indigo-100 rounded-lg hover:bg-indigo-200 text-indigo-700 font-bold"
                                                        title="ตั้งค่าแบบประเมิน">
                                                    <i class="fas fa-tasks"></i>
                                                </button>

                                                <form method="POST"
                                                      action="{{ route('categories.destroy', $category->id) }}"
                                                      class="delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                            class="delete-button p-2 bg-gray-100 rounded-lg hover:bg-gray-200"
                                                            title="ลบ">
                                                        <i class="fas fa-trash text-red-600"></i>
                                                    </button>
                                                </form>
                                                {{-- ✅✅✅ END: โค้ดที่แก้ไข ✅✅✅ --}}
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center p-8 text-gray-500">
                                            ยังไม่มีข้อมูลประเภท
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- เพจิเนชัน: แสดงเมื่อใช้ paginate() --}}
                    @if (method_exists($categories, 'hasPages') && $categories->hasPages())
                        <div class="p-4 bg-gray-50 border-t mt-4 rounded-b-2xl">
                            {{ $categories->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
{{-- Evaluation Config Modal --}}
<div id="eval-config-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-600 bg-opacity-75 backdrop-blur-sm" aria-hidden="true" onclick="closeEvalModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full animate-scale-up">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-tasks text-indigo-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="eval-modal-title">ตั้งค่าแบบประเมิน</h3>
                        <div class="mt-2">
                             {{-- Calculation Logic Info --}}
                            <div class="mb-6 p-4 bg-blue-50 border border-blue-100 rounded-lg flex items-start gap-3">
                                <div class="flex-shrink-0 mt-0.5 text-blue-500">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <div class="text-sm text-blue-800">
                                    <h4 class="font-bold mb-1">เกณฑ์การคำนวณคะแนนอัจฉริยะ (Smart Rating)</h4>
                                    <ul class="list-disc pl-4 space-y-1 text-blue-700/80">
                                        <li><strong>คะแนนรวม (เต็ม 5):</strong> มาจาก (คะแนนสต๊อก + คะแนนความพึงพอใจ) ÷ 2</li>
                                        <li><strong>1. คะแนนสต๊อก (Inventory Efficiency):</strong>
                                            <ul class="list-disc pl-4 text-xs">
                                                <li><strong>หมุนเวียนบ่อย (3+ ครั้ง/ไตรมาส):</strong> 5.0 คะแนน (Hot Item)</li>
                                                <li><strong>หมุนเวียนปกติ:</strong> 4.0 คะแนน</li>
                                                <li><strong>ไม่เคลื่อนไหว (Deadstock):</strong>
                                                    <br>- สินค้าแพง (>=500): 1.0 คะแนน (เงินจม)
                                                    <br>- สินค้าถูก/ไม่มีราคา: 3.0 คะแนน (เป็นกลาง)
                                                </li>
                                            </ul>
                                        </li>
                                        <li><strong>2. คะแนนความพึงพอใจ:</strong> มาจากแบบสอบถามเฉลี่ย (แย่=1, ดี=5)</li>
                                    </ul>
                                </div>
                            </div>

                            <p class="text-sm text-gray-500 mb-4">กำหนดหัวข้อคำถามสำหรับการประเมินอุปกรณ์ในประเภทนี้ (เช่น คุณภาพ, ความคุ้มค่า)</p>
                            
                            {{-- Questions Builder --}}
                            <div id="questions-builder" class="space-y-3 max-h-[60vh] overflow-y-auto pr-2">
                                {{-- Dynamic Inputs will go here --}}
                            </div>

                            <button type="button" onclick="addQuestionRow()" class="mt-4 inline-flex items-center px-3 py-2 border border-dashed border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors w-full justify-center">
                                <i class="fas fa-plus mr-2 text-gray-400"></i> เพิ่มหัวข้อคำถาม
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                <button type="button" onclick="saveEvalConfig()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                    บันทึก
                </button>
                <button type="button" onclick="closeEvalModal()" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="current-category-id">

{{-- Templates for JS --}}
<template id="question-row-template">
    <div class="question-row bg-gray-50 p-3 rounded-lg border border-gray-200 relative group animate-fade-in-up">
        <button type="button" onclick="removeQuestionRow(this)" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 p-1 opacity-0 group-hover:opacity-100 transition-opacity" title="ลบหัวข้อนี้">
            <i class="fas fa-times"></i>
        </button>
        <div class="grid grid-cols-1 gap-2">
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">หัวข้อคำถาม (Label)</label>
                <input type="text" class="q-label w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="เช่น ความทนทาน">
            </div>
            {{-- Simplified Options: Fixed 3 Types for now to keep UI simple --}}
            <div class="mt-2 text-xs text-gray-500">
                <span class="mr-2"><i class="fas fa-star text-yellow-400"></i> ตัวเลือกมาตรฐาน:</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">แย่/น้อย</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">ยังไม่ใช้</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">ดี/มาก</span>
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<style>
    .animate-fade-in-up { animation: fadeInUp 0.3s ease-out; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
<script>
    // --- Global Variables ---
    let currentCategoryId = null;
    const defaultOptions = [
        { value: 1, emoji: '👎', text: 'แย่/น้อย', class: 'text-red-600' }, 
        { value: 2, emoji: '📦', text: 'ยังไม่เคยใช้งาน', class: 'text-gray-500' }, 
        { value: 3, emoji: '👍', text: 'ดี/มาก', class: 'text-green-600' }
    ];

    function openEvalConfig(categoryId, categoryName) {
        currentCategoryId = categoryId;
        document.getElementById('current-category-id').value = categoryId;
        document.getElementById('eval-modal-title').innerText = `ตั้งค่าแบบประเมิน: ${categoryName}`;
        document.getElementById('questions-builder').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-indigo-500"></i> กำลังโหลด...</div>';
        
        document.getElementById('eval-config-modal').classList.remove('hidden');

        // Fetch Config
        fetch(`/categories/${categoryId}/evaluation-config`)
            .then(res => res.json())
            .then(data => {
                const questions = data.config && data.config.length ? data.config : getDefaultQuestions();
                renderBuilder(questions);
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลได้', 'error');
                closeEvalModal();
            });
    }

    function closeEvalModal() {
        document.getElementById('eval-config-modal').classList.add('hidden');
    }

    function getDefaultQuestions() {
        return [
            { id: 'q1', label: 'คุณภาพวัสดุ (Material Quality)', options: defaultOptions },
            { id: 'q2', label: 'ความเหมาะสมกับงาน (Suitability)', options: defaultOptions },
            { id: 'q3', label: 'ความคุ้มค่า (Worthiness)', options: defaultOptions }
        ];
    }

    function renderBuilder(questions) {
        const container = document.getElementById('questions-builder');
        container.innerHTML = '';
        questions.forEach(q => addQuestionRow(q.label));
    }

    function addQuestionRow(labelValue = '') {
        const template = document.getElementById('question-row-template');
        const clone = template.content.cloneNode(true);
        const input = clone.querySelector('.q-label');
        input.value = labelValue;
        
        document.getElementById('questions-builder').appendChild(clone);
        if(!labelValue) input.focus();
    }

    function removeQuestionRow(btn) {
        btn.closest('.question-row').remove();
    }

    function saveEvalConfig() {
        if (!currentCategoryId) return;

        const rows = document.querySelectorAll('#questions-builder .question-row');
        const questions = [];
        
        rows.forEach((row, index) => {
            const label = row.querySelector('.q-label').value.trim();
            if (label) {
                questions.push({
                    id: `q${index + 1}`, // Generate ID automatically
                    label: label,
                    options: defaultOptions // Use default options for now (Simple MVP)
                });
            }
        });

        if (questions.length === 0) {
            Swal.fire('แจ้งเตือน', 'กรุณาระบุหัวข้อคำถามอย่างน้อย 1 ข้อ', 'warning');
            return;
        }

        const btn = document.querySelector('#eval-config-modal button[onclick="saveEvalConfig()"]');
        const originalText = btn.innerText;
        btn.innerText = 'กำลังบันทึก...';
        btn.disabled = true;

        fetch(`/categories/${currentCategoryId}/evaluation-config`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ custom_questions: questions })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'บันทึกเรียบร้อยแล้ว', timer: 1500, showConfirmButton: false });
                closeEvalModal();
            } else {
                Swal.fire('Error', data.message || 'เกิดข้อผิดพลาด', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'ไม่สามารถบันทึกได้', 'error');
        })
        .finally(() => {
            btn.innerText = originalText;
            btn.disabled = false;
        });
    }

    // ✅ Delete Button Logic
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.delete-button').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('form');
                Swal.fire({
                    title: 'ยืนยันการลบ?',
                    text: "คุณต้องการลบประเภทนี้ใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ลบข้อมูล',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush