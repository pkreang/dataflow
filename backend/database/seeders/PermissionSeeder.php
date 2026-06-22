<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'dashboard' => ['read'],
            'user_access' => ['create', 'read', 'update', 'delete'],
            'role_access' => ['create', 'read', 'update', 'delete'],
            'permission_access' => ['read'],
        ];

        $allowedNames = [];
        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                Permission::updateOrCreate(
                    [
                        'name' => $name,
                        'guard_name' => 'web',
                    ],
                    [
                        'module' => $module,
                        'action' => $action,
                    ]
                );
                $allowedNames[] = $name;
            }
        }

        $exactPermissions = [
            ['name' => 'manage profile', 'module' => 'company', 'action' => 'manage'],
            ['name' => 'manage_settings', 'module' => 'settings', 'action' => 'manage'],
            ['name' => 'approval.approve', 'module' => 'approval', 'action' => 'approve'],
            ['name' => 'submission.create_for_others', 'module' => 'submission', 'action' => 'create_for_others'],
            ['name' => 'manage dashboards', 'module' => 'dashboard', 'action' => 'manage'],
            ['name' => 'view_purchase_requests', 'module' => 'purchase_requests', 'action' => 'read'],
            ['name' => 'view_purchase_orders',   'module' => 'purchase_orders',   'action' => 'read'],
            ['name' => 'purchase_order.create',  'module' => 'purchase_orders',   'action' => 'create'],
        ];
        foreach ($exactPermissions as $item) {
            Permission::updateOrCreate(
                ['name' => $item['name'], 'guard_name' => 'web'],
                ['module' => $item['module'], 'action' => $item['action']]
            );
            $allowedNames[] = $item['name'];
        }

        // Remove permissions that are not aligned with current menus/features.
        Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $allowedNames)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
