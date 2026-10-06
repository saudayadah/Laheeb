<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * The whole permission catalogue. Milestones 2+ use the later groups;
     * defining them now keeps role setup stable across releases.
     */
    public const PERMISSIONS = [
        'settings.manage',
        'users.manage',
        'customers.view',
        'customers.manage',
        'routes.manage',
        'prices.view',
        'prices.manage',
        'products.view',
        'products.manage',
        'imports.run',
        'orders.manage',
        'invoices.manage',
        'invoices.void',
        'invoices.reclassify',
        'receipts.manage',
        'closes.approve',
        'expenses.manage',
        'expenses.approve',
        'suppliers.manage',
        'employees.manage',
        'advances.manage',
        'advances.approve',
        'payroll.manage',
        'payroll.approve',
        'reports.view',
        'balances.view',
        'deliveries.own',
    ];

    public const ROLES = [
        'owner' => '*',
        'accountant' => [
            'customers.view', 'customers.manage', 'routes.manage',
            'prices.view', 'products.view', 'imports.run',
            'invoices.manage', 'invoices.reclassify', 'receipts.manage',
            'closes.approve', 'expenses.manage', 'expenses.approve',
            'suppliers.manage', 'employees.manage', 'advances.manage',
            'payroll.manage', 'reports.view', 'balances.view',
        ],
        'clerk' => [
            'customers.view', 'customers.manage', 'products.view', 'orders.manage',
        ],
        'driver' => [
            'deliveries.own',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($permissions === '*' ? self::PERMISSIONS : $permissions);
        }
    }
}
