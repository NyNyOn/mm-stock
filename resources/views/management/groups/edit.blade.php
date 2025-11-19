@extends('layouts.app')
@section('header', 'แก้ไขกลุ่ม: ' . $group->name)
@section('content')
<div class="container px-4 mx-auto">
    <div class="max-w-xl p-6 mx-auto soft-card rounded-2xl">
        <form action="{{ route('management.groups.update', $group) }}" method="POST">
            @csrf
            @method('PUT')
            @include('management.groups.partials._form')
            <div class="mt-6">
                <button type="submit" class="btn-primary">อัปเดต</button>
                <a href="{{ route('management.groups.index') }}" class="btn-secondary">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>
@endsection
