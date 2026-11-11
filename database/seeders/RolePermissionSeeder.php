<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSuperadminPermissions();
        $this->seedAdminPermissions();
        $this->seedLatberPermissions();
    }

    private function seedSuperadminPermissions(): void
    {
        $allPermissionIds = Permission::pluck('id');

        foreach ($allPermissionIds as $permissionId) {
            RolePermission::firstOrCreate(
                [
                    'role' => 'superadmin',
                    'permission_id' => $permissionId,
                ]
            );
        }
    }

    private function seedAdminPermissions(): void
    {
        $adminPermissions = [
            'view_events',
            'manage_events',
            'view_sessions',
            'manage_sessions',
            'view_invoices',
            'create_invoices',
            'edit_invoices',
            'delete_invoices',
            'change_invoice_status',
            'view_reports',
            'view_rekap',
            'view_expenses',
            'manage_expenses',
            'view_latbers',
            'manage_latbers',
            'view_latber_sessions',
            'manage_latber_sessions',
        ];

        foreach ($adminPermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            
            if ($permission) {
                RolePermission::firstOrCreate(
                    [
                        'role' => 'admin',
                        'permission_id' => $permission->id,
                    ]
                );
            }
        }
    }

    private function seedLatberPermissions(): void
    {
        $latberPermissions = [
            'view_latbers',
            'manage_latbers',
            'view_latber_sessions',
            'manage_latber_sessions',
        ];

        foreach ($latberPermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            
            if ($permission) {
                RolePermission::firstOrCreate(
                    [
                        'role' => 'latber',
                        'permission_id' => $permission->id,
                    ]
                );
            }
        }
    }
}
