@extends('layouts.app')
@section('header', 'สร้างกลุ่มใหม่')
@section('content')
<div class="container px-4 mx-auto">
    <div class="max-w-xl p-6 mx-auto soft-card rounded-2xl">
        <form action="{{ route('management.groups.store') }}" method="POST">
            @csrf
            @include('management.groups.partials._form', ['group' => new \App\Models\UserGroup()])
            <div class="mt-6">
                <button type="submit" class="btn-primary">บันทึก</button>
                <a href="{{ route('management.groups.index') }}" class="btn-secondary">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>
@endsection
