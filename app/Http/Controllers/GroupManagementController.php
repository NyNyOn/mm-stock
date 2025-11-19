<?php

namespace App\Http\Controllers;

use App\Models\UserGroup;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;

class GroupManagementController extends Controller
{
    /**
     * The constructor now uses the corrected 'manage-groups' Gate.
     */
    public function __construct()
    {
        $this->middleware('can:manage-groups')->except('updatePermissions');
    }

    /**
     * Display a listing of groups based on user's hierarchy level.
     */
    public function index()
    {
        $user = Auth::user();
        $permissions = Permission::orderBy('name')->get();

        // ✅ NEW LOGIC: Filter groups based on user's role level.
        if ($user->getRoleLevel() >= 90) {
            // If user is IT or Owner (level 90+), show all groups.
            $groups = UserGroup::with('permissions', 'serviceUserRoles')->orderBy('hierarchy_level', 'desc')->get();
        } else {
            // If user is an Admin (or lower), only show groups with a level strictly less than their own.
            $groups = UserGroup::with('permissions', 'serviceUserRoles')
                ->where('hierarchy_level', '<', $user->getRoleLevel())
                ->orderBy('hierarchy_level', 'desc')
                ->get();
        }

        return view('management.groups.index', compact('groups', 'permissions'));
    }

    /**
     * Show the form for creating a new group.
     * Note: Access to the button/link for this should be restricted in the view.
     */
    public function create()
    {
        // Add an extra layer of protection here
        if (Auth::user()->getRoleLevel() < 90) {
            abort(403, 'คุณไม่มีสิทธิ์สร้างกลุ่มใหม่');
        }
        return view('management.groups.create');
    }

    /**
     * Store a newly created group.
     */
    public function store(Request $request)
    {
        if (Auth::user()->getRoleLevel() < 90) {
            abort(403, 'คุณไม่มีสิทธิ์สร้างกลุ่มใหม่');
        }
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:user_groups,name',
            'slug' => 'required|string|max:255|unique:user_groups,slug|alpha_dash',
            'description' => 'nullable|string',
            'hierarchy_level' => 'required|integer|min:1',
        ]);
        UserGroup::create($validatedData);
        return redirect()->route('management.groups.index')->with('success', 'สร้างกลุ่มใหม่เรียบร้อยแล้ว');
    }

    /**
     * Show the form for editing a group.
     */
    public function edit(UserGroup $group)
    {
        // ✅ ADDED PROTECTION: Prevent Admins from editing higher-level groups via direct URL.
        if (Auth::user()->getRoleLevel() <= $group->hierarchy_level) {
            abort(403, 'คุณไม่มีสิทธิ์แก้ไขกลุ่มนี้');
        }
        return view('management.groups.edit', compact('group'));
    }

    /**
     * Update an existing group.
     */
    public function update(Request $request, UserGroup $group)
    {
        // ✅ ADDED PROTECTION: Double-check authorization before updating.
        if (Auth::user()->getRoleLevel() <= $group->hierarchy_level) {
            abort(403, 'คุณไม่มีสิทธิ์แก้ไขกลุ่มนี้');
        }
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:user_groups,name,' . $group->id,
            'slug' => 'required|string|max:255|unique:user_groups,slug,' . $group->id . '|alpha_dash',
            'description' => 'nullable|string',
            'hierarchy_level' => 'required|integer|min:1',
        ]);
        $group->update($validatedData);
        return redirect()->route('management.groups.index')->with('success', 'อัปเดตกลุ่มเรียบร้อยแล้ว');
    }

    /**
     * Delete a group.
     */
    public function destroy(UserGroup $group)
    {
        // ✅ ADDED PROTECTION: Double-check authorization before deleting.
        if (Auth::user()->getRoleLevel() <= $group->hierarchy_level) {
            abort(403, 'คุณไม่มีสิทธิ์ลบกลุ่มนี้');
        }
        if (in_array($group->slug, ['owner', 'it', 'admin'])) {
            return back()->with('error', "ไม่สามารถลบกลุ่มหลักของระบบได้");
        }
        if ($group->serviceUserRoles()->count() > 0) {
            return back()->with('error', "ไม่สามารถลบกลุ่มได้ เนื่องจากยังมีผู้ใช้งานอยู่");
        }
        $group->delete();
        return redirect()->route('management.groups.index')->with('success', "ลบกลุ่มเรียบร้อยแล้ว");
    }

    /**
     * Update only the permissions for a group.
     */
    public function updatePermissions(Request $request, UserGroup $group)
    {
        Gate::authorize('permission:manage');
        $group->permissions()->sync($request->input('permissions', []));
        return back()->with('success', 'อัปเดตสิทธิ์ของกลุ่ม ' . $group->name . ' เรียบร้อยแล้ว');
    }
}
