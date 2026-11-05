<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderBy('category')->orderBy('name')->get()
            ->groupBy('category');

        return response()->json($permissions);
    }

    public function getRolePermissions($role)
    {
        $permissions = Permission::all();
        
        $rolePermissions = RolePermission::where('role', $role)
            ->pluck('permission_id')
            ->toArray();

        return response()->json([
            'role' => $role,
            'permissions' => $permissions,
            'assigned_permission_ids' => $rolePermissions,
        ]);
    }

    public function updateRolePermissions(Request $request, $role)
    {
        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        RolePermission::where('role', $role)->delete();

        foreach ($request->permission_ids as $permissionId) {
            RolePermission::create([
                'role' => $role,
                'permission_id' => $permissionId,
            ]);
        }

        return response()->json([
            'message' => 'Permission role berhasil diperbarui',
        ]);
    }

    public function getUserPermissions($userId)
    {
        $permissions = Permission::all();
        
        $userPermissions = UserPermission::where('user_id', $userId)
            ->get()
            ->keyBy('permission_id')
            ->map(fn($up) => $up->granted)
            ->toArray();

        return response()->json([
            'user_id' => $userId,
            'permissions' => $permissions,
            'user_permissions' => $userPermissions,
        ]);
    }

    public function updateUserPermissions(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        UserPermission::where('user_id', $userId)->delete();

        foreach ($request->permissions as $permissionId => $granted) {
            UserPermission::create([
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'granted' => $granted,
            ]);
        }

        return response()->json([
            'message' => 'Permission user berhasil diperbarui',
        ]);
    }
}
