<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * All KMSAR roles — KMSAR_ARCHITECTURE.md §6 (Role Definitions).
     *
     * @var list<string>
     */
    private const ROLE_SLUGS = [
        'super_admin',
        'ovpri_admin',
        'cdaic_admin',
        'college_dean',
        'unit_head',
        'faculty',
        'registrar',
        'viewer',
    ];

    /**
     * All Spatie permissions from KMSAR_ARCHITECTURE.md §6 (Key Permissions).
     *
     * @var list<string>
     */
    private const PERMISSIONS = [
        'research.view_own',
        'research.view_college',
        'research.view_all',
        'research.create',
        'research.update',
        'research.submit',
        'research.revise',
        'approval.endorse',
        'approval.approve',
        'approval.return',
        'document.upload',
        'document.download',
        'report.view_college',
        'report.view_university',
        'report.export',
        'admin.users',
        'admin.colleges',
        'admin.audit_logs',
    ];

    public function run(): void
    {
        Role::query()
            ->where('name', 'co_author')
            ->where('guard_name', self::GUARD)
            ->delete();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        foreach (self::ROLE_SLUGS as $slug) {
            Role::findOrCreate($slug, self::GUARD);
        }

        $all = Permission::query()->where('guard_name', self::GUARD)->get();

        $assign = static function (string $roleSlug, array $permissionNames): void {
            $role = Role::findOrCreate($roleSlug, self::GUARD);
            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', self::GUARD)
                    ->whereIn('name', $permissionNames)
                    ->get()
            );
        };

        // super_admin: view all + full admin (§6 matrix: Can View All, Can Admin)
        Role::findOrCreate('super_admin', self::GUARD)->syncPermissions($all);

        // ovpri_admin / cdaic_admin: approve at OVPRI tier, university visibility, reports, partial admin (audit only)
        $ovpriCdaic = [
            'research.view_all',
            'approval.approve',
            'approval.return',
            'document.download',
            'report.view_college',
            'report.view_university',
            'report.export',
            'admin.audit_logs',
        ];
        $assign('ovpri_admin', $ovpriCdaic);
        $assign('cdaic_admin', $ovpriCdaic);

        // college_dean / unit_head: own college/unit queue (endorse / return), college reports
        $deanUnitHead = [
            'research.view_college',
            'approval.endorse',
            'approval.return',
            'document.download',
            'report.view_college',
        ];
        $assign('college_dean', $deanUnitHead);
        $assign('unit_head', $deanUnitHead);

        // faculty: submit and manage own research + documents
        $assign('faculty', [
            'research.view_own',
            'research.create',
            'research.update',
            'research.submit',
            'research.revise',
            'document.upload',
            'document.download',
        ]);

        // registrar: approved-only visibility enforced in policy; read documents as needed
        $assign('registrar', [
            'research.view_all',
            'document.download',
        ]);

        // viewer: read-only access to research records associated with the user
        $assign('viewer', [
            'research.view_own',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
