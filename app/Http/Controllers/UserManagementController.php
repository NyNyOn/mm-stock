<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Permission;
use App\Models\ServiceUserRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserManagementController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('user:manage');
        $query = User::with('serviceUserRole.userGroup')->orderBy('fullname', 'asc');
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('fullname', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('username', 'LIKE', "%{$searchTerm}%");
            });
        }
        $users = $query->get();
        $groups = UserGroup::orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();
        return view('management.users.index', compact('users', 'groups', 'permissions'));
    }

    public function update(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('user:manage'); // 1. Does the user have basic permission to be on this page?
        $request->validate(['group_id' => 'required|integer|exists:user_groups,id']);

        $actingUser = Auth::user();
        $newGroup = UserGroup::find($request->input('group_id'));

        // ✅ NEW LOGIC: Clean, simple, and powerful.
        // 2. Can the acting user manage the target user? (Checks hierarchy level)
        $this->authorize('manage-user-role', $targetUser);

        // 3. Is the acting user's level high enough to assign someone to the new group?
        // This prevents an Admin (50) from assigning another user to the IT group (90).
        if ($actingUser->getRoleLevel() <= $newGroup->hierarchy_level) {
            abort(403, "คุณไม่มีสิทธิ์กำหนดให้ผู้ใช้อยู่ในกลุ่ม '{$newGroup->name}' เนื่องจากมีระดับสิทธิ์ไม่เพียงพอ");
        }

        ServiceUserRole::updateOrCreate(
            ['user_id' => $targetUser->id],
            ['group_id' => $request->input('group_id')]
        );

        return back()->with('success', 'อัปเดตกลุ่มให้ ' . $targetUser->fullname . ' เรียบร้อยแล้ว');
    }

    public function bulkUpdate(Request $request)
    {
        // DEBUG LOGGING
        \Log::info('Bulk Update Request:', $request->all());

        $this->authorize('user:manage');
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer', // Relaxed validation: check existence in loop
            'group_id' => 'required|integer|exists:user_groups,id'
        ]);

        $actingUser = Auth::user();
        $newGroup = UserGroup::find($request->input('group_id'));
        $successCount = 0;
        $failCount = 0;

        // Check if acting user can assign to this group level
        if ($actingUser->getRoleLevel() <= $newGroup->hierarchy_level) {
             \Log::warning('Bulk Update Failed: Insufficient Role Level');
            return back()->with('error', "คุณไม่มีสิทธิ์กำหนดกลุ่ม '{$newGroup->name}' (ระดับสิทธิ์ไม่เพียงพอ)");
        }

        foreach ($request->user_ids as $userId) {
            \Log::info("Looping User ID: $userId");
            $targetUser = User::find($userId);
            if (!$targetUser) {
                \Log::warning("User ID $userId not found in DB");
                continue;
            }

            // Check if acting user can manage this specific target user
            $canManage = $actingUser->can('manage-user-role', $targetUser);
            \Log::info("Can Manage User $userId? : " . ($canManage ? 'YES' : 'NO'));

            if ($canManage) {
                // EXPLICIT SAVE LOGIC (Safer for non-incrementing PKs)
                $role = ServiceUserRole::where('user_id', $targetUser->id)->first();
                if (!$role) {
                    $role = new ServiceUserRole();
                    $role->user_id = $targetUser->id;
                }
                $role->group_id = $newGroup->id;
                $role->save();
                
                \Log::info("Saved Role for User $userId -> Group {$newGroup->id}");
                $successCount++;
            } else {
                \Log::warning("Bulk Update Failed for User ID $userId: Cannot manage target user");
                $failCount++;
            }
        }
        
        \Log::info("Bulk Update Result: Success=$successCount, Fail=$failCount");

        if ($failCount > 0) {
            return back()->with('success', "อัปเดตสำเร็จ $successCount คน (ไม่สามารถอัปเดต $failCount คน เนื่องจากระดับสิทธิ์)");
        }

        return back()->with('success', "อัปเดตกลุ่มสำเร็จ $successCount คน");
    }

    public function removeGroup(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('user:manage'); // 1. Basic permission check.

        // ✅ NEW LOGIC: One gate to rule them all.
        // 2. Can the acting user manage the target user? (Checks hierarchy level)
        $this->authorize('manage-user-role', $targetUser);

        if ($targetUser->serviceUserRole) {
            $targetUser->serviceUserRole->delete();
            return back()->with('success', 'นำผู้ใช้ ' . $targetUser->fullname . ' ออกจากกลุ่มเรียบร้อยแล้ว');
        }
        return back()->with('error', 'ไม่พบข้อมูลกลุ่มของผู้ใช้นี้');
    }
}
