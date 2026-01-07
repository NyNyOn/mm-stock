<?php

namespace App\Http\Controllers;

use App\Models\CustomObjective;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomObjectiveController extends Controller
{
    public function index()
    {
        $objectives = CustomObjective::orderBy('created_at', 'desc')->get();
        return view('custom_objectives.index', compact('objectives'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:custom_objectives,name',
        ]);

        CustomObjective::create([
            'name' => $request->name,
            'type' => 'general_use',
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'เพิ่มวัตถุประสงค์เรียบร้อยแล้ว');
    }

    public function update(Request $request, CustomObjective $customObjective)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:custom_objectives,name,' . $customObjective->id,
        ]);

        $customObjective->update([
            'name' => $request->name,
        ]);

        return redirect()->back()->with('success', 'แก้ไขวัตถุประสงค์เรียบร้อยแล้ว');
    }

    public function destroy(CustomObjective $customObjective)
    {
        $customObjective->delete();
        return redirect()->back()->with('success', 'ลบวัตถุประสงค์เรียบร้อยแล้ว');
    }

    public function toggleStatus(CustomObjective $customObjective)
    {
        $customObjective->update([
            'is_active' => !$customObjective->is_active
        ]);

        return redirect()->back()->with('success', 'อัปเดตสถานะเรียบร้อยแล้ว');
    }
}
