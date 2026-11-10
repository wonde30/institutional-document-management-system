<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions
        $permissions = [
            // User & Department & RBAC Management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
            'departments.view',
            'departments.manage',

            // Documents
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.publish',
            'documents.download',

            // Correspondence
            'correspondence.view',
            'correspondence.create',
            'correspondence.assign',
            'correspondence.dispatch',
            'correspondence.complete',

            // Memos
            'memos.view',
            'memos.create',
            'memos.acknowledge',
            'memos.forward',

            // Workflow
            'workflow.transition',
            'workflow.manage',

            // Archive
            'archive.view',
            'archive.transfer',
            'archive.retrieve',
            'archive.request_disposal',
            'archive.approve_disposal',
            'archive.execute_disposal',

            // Reports & Audit
            'reports.view',
            'reports.export',
            'audit.view',
            'settings.view',
            'settings.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Roles definition
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        // Super Admin receives all permissions via Gate::before or direct sync
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'departments.view', 'departments.manage',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.manage',
            'permissions.view', 'permissions.manage',
            'documents.view', 'documents.create', 'documents.update', 'documents.delete', 'documents.download',
            'correspondence.view', 'correspondence.create',
            'memos.view', 'memos.create', 'memos.forward', 'memos.acknowledge',
            'workflow.transition', 'workflow.manage',
            'archive.view', 'archive.transfer',
            'reports.view', 'reports.export',
            'audit.view', 'settings.view', 'settings.manage',
        ]);

        $deptHead = Role::firstOrCreate(['name' => 'Department Head', 'guard_name' => 'web']);
        $deptHead->syncPermissions([
            'departments.view',
            'users.view',
            'documents.view', 'documents.create', 'documents.update', 'documents.publish', 'documents.download',
            'correspondence.view', 'correspondence.create', 'correspondence.assign', 'correspondence.complete',
            'memos.view', 'memos.create', 'memos.acknowledge', 'memos.forward',
            'workflow.transition',
            'archive.view',
            'reports.view', 'reports.export',
        ]);

        $docController = Role::firstOrCreate(['name' => 'Document Controller', 'guard_name' => 'web']);
        $docController->syncPermissions([
            'departments.view',
            'documents.view', 'documents.create', 'documents.update', 'documents.publish', 'documents.download',
            'correspondence.view',
            'memos.view', 'memos.create', 'memos.forward', 'memos.acknowledge',
            'workflow.transition',
            'archive.transfer',
            'reports.view',
        ]);

        $recordsOfficer = Role::firstOrCreate(['name' => 'Records Officer', 'guard_name' => 'web']);
        $recordsOfficer->syncPermissions([
            'departments.view',
            'documents.view', 'documents.create', 'documents.download',
            'correspondence.view', 'correspondence.create', 'correspondence.assign', 'correspondence.dispatch', 'correspondence.complete',
            'memos.view', 'memos.create', 'memos.forward', 'memos.acknowledge',
            'archive.view', 'archive.transfer',
            'reports.view',
        ]);

        $archivist = Role::firstOrCreate(['name' => 'Archivist', 'guard_name' => 'web']);
        $archivist->syncPermissions([
            'departments.view',
            'documents.view', 'documents.download',
            'correspondence.view',
            'memos.view', 'memos.create', 'memos.forward', 'memos.acknowledge',
            'archive.view', 'archive.transfer', 'archive.retrieve', 'archive.request_disposal', 'archive.execute_disposal',
            'reports.view',
            'audit.view',
        ]);

        $staff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $staff->syncPermissions([
            'departments.view',
            'documents.view', 'documents.create', 'documents.download',
            'memos.view', 'memos.create', 'memos.forward', 'memos.acknowledge',
        ]);
    }
}
