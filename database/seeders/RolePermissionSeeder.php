<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_employees', 'create_employees', 'edit_employees', 'delete_employees',
            'view_departments', 'create_departments', 'edit_departments', 'delete_departments',
            'view_positions', 'create_positions', 'edit_positions', 'delete_positions',
            'view_contracts', 'create_contracts', 'edit_contracts', 'delete_contracts',
            'view_leaves', 'create_leaves', 'edit_leaves', 'delete_leaves', 'approve_leaves',
            'view_attendance', 'manage_attendance',
            'view_payrolls', 'process_payrolls', 'pay_payrolls',
            'view_documents', 'upload_documents', 'delete_documents',
            'view_trainings', 'create_trainings', 'edit_trainings', 'delete_trainings',
            'view_recruitments', 'create_recruitments', 'edit_recruitments', 'delete_recruitments',
            'view_performances', 'create_performances', 'edit_performances', 'delete_performances',
            'view_reports', 'view_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);
        $superAdmin->syncPermissions(Permission::all());

        $adminOrg = Role::firstOrCreate([
            'name' => 'admin_org',
            'guard_name' => 'web',
        ]);
        $adminOrg->syncPermissions(Permission::all());

        $manager = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);
        $manager->syncPermissions([
            'view_employees',
            'view_departments',
            'view_positions',
            'view_contracts',
            'view_leaves',
            'create_leaves',
            'approve_leaves',
            'view_attendance',
            'manage_attendance',
            'view_documents',
            'upload_documents',
            'view_trainings',
            'view_recruitments',
            'view_performances',
            'create_performances',
            'edit_performances',
            'view_reports',
        ]);

        $employee = Role::firstOrCreate([
            'name' => 'employee',
            'guard_name' => 'web',
        ]);
        $employee->syncPermissions([
            'view_leaves',
            'create_leaves',
            'view_attendance',
            'view_documents',
            'upload_documents',
            'view_trainings',
        ]);
    }
}