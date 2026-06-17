<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'group' => 'dashboard'],
            // Bond Requests
            ['name' => 'View Bond Requests', 'slug' => 'bond-requests.view', 'group' => 'bond-requests'],
            ['name' => 'Create Bond Requests', 'slug' => 'bond-requests.create', 'group' => 'bond-requests'],
            ['name' => 'Update Bond Requests', 'slug' => 'bond-requests.update', 'group' => 'bond-requests'],
            ['name' => 'Delete Bond Requests', 'slug' => 'bond-requests.delete', 'group' => 'bond-requests'],
            ['name' => 'Approve Bond Requests', 'slug' => 'bond-requests.approve', 'group' => 'bond-requests'],
            ['name' => 'Notarize Bond Requests', 'slug' => 'bond-requests.notarize', 'group' => 'bond-requests'],
            // Obligees
            ['name' => 'View Obligees', 'slug' => 'obligees.view', 'group' => 'obligees'],
            ['name' => 'Create Obligees', 'slug' => 'obligees.create', 'group' => 'obligees'],
            ['name' => 'Update Obligees', 'slug' => 'obligees.update', 'group' => 'obligees'],
            ['name' => 'Delete Obligees', 'slug' => 'obligees.delete', 'group' => 'obligees'],
            // Principals
            ['name' => 'View Principals', 'slug' => 'principals.view', 'group' => 'principals'],
            ['name' => 'Create Principals', 'slug' => 'principals.create', 'group' => 'principals'],
            ['name' => 'Update Principals', 'slug' => 'principals.update', 'group' => 'principals'],
            ['name' => 'Delete Principals', 'slug' => 'principals.delete', 'group' => 'principals'],
            // Confirmations
            ['name' => 'View Confirmations', 'slug' => 'certifications.view-assigned', 'group' => 'certifications'],
            ['name' => 'View Audit Logs', 'slug' => 'audit-logs.view', 'group' => 'audit-logs'],
            // Maintenance
            ['name' => 'View Maintenance', 'slug' => 'maintenance.view', 'group' => 'maintenance'],
            ['name' => 'Manage Maintenance', 'slug' => 'maintenance.manage', 'group' => 'maintenance'],
            // Deposits
            ['name' => 'View Deposits (Admin)', 'slug' => 'deposits.view', 'group' => 'payments'],
            ['name' => 'Submit Deposit', 'slug' => 'deposits.create', 'group' => 'payments'],
            ['name' => 'Approve Deposits', 'slug' => 'deposits.approve', 'group' => 'payments'],
            // Transactions
            ['name' => 'View Transactions', 'slug' => 'transactions.view', 'group' => 'payments'],
            ['name' => 'View Payment Histories', 'slug' => 'payment-histories.view', 'group' => 'payments'],
            // Users
            ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'group' => 'users'],
            // Confirmation Templates
            ['name' => 'View Confirmation Templates', 'slug' => 'certificate-templates.view', 'group' => 'certificate-templates'],
            ['name' => 'Manage Confirmation Templates', 'slug' => 'certificate-templates.manage', 'group' => 'certificate-templates'],
            // Backups
            ['name' => 'Manage Backups', 'slug' => 'backups.manage', 'group' => 'backups'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $allPermissionIds = Permission::pluck('id');

        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => RoleSlug::SuperAdmin->value,
                'description' => 'Full system access',
                'permissions' => $allPermissionIds,
            ],
            [
                'name' => 'Requester',
                'slug' => RoleSlug::Requester->value,
                'description' => 'Creates bond requests',
                'permissions' => Permission::whereIn('slug', [
                    'dashboard.view',
                    'bond-requests.view',
                    'bond-requests.create',
                    'bond-requests.update',
                    'obligees.view',
                    'principals.view',
                    'deposits.create',
                    'transactions.view',
                    'payment-histories.view',
                ])->pluck('id'),
            ],
            [
                'name' => 'Encoder / Editor',
                'slug' => RoleSlug::Encoder->value,
                'description' => 'Edits bond data and master records',
                'permissions' => Permission::whereIn('slug', [
                    'dashboard.view',
                    'bond-requests.view',
                    'bond-requests.create',
                    'bond-requests.update',
                    'obligees.view',
                    'obligees.create',
                    'obligees.update',
                    'principals.view',
                    'principals.create',
                    'principals.update',
                    'maintenance.view',
                    'maintenance.manage',
                    'deposits.create',
                    'transactions.view',
                    'payment-histories.view',
                ])->pluck('id'),
            ],
            [
                'name' => 'Approver',
                'slug' => RoleSlug::Approver->value,
                'description' => 'Approves or rejects bond requests',
                'permissions' => Permission::whereIn('slug', [
                    'dashboard.view',
                    'bond-requests.view',
                    'bond-requests.approve',
                    'obligees.view',
                    'principals.view',
                    'deposits.view',
                    'deposits.create',
                    'deposits.approve',
                    'transactions.view',
                    'payment-histories.view',
                ])->pluck('id'),
            ],
            [
                'name' => 'Notary / Signatory',
                'slug' => RoleSlug::Notary->value,
                'description' => 'Attorneys who manage signatures and view assigned certificates',
                'permissions' => Permission::whereIn('slug', [
                    'certifications.view-assigned',
                ])->pluck('id'),
            ],
        ];

        foreach ($roles as $roleData) {
            $permissionIds = $roleData['permissions'];
            unset($roleData['permissions']);
            $role = Role::updateOrCreate(['slug' => $roleData['slug']], $roleData);
            $role->permissions()->sync($permissionIds);
        }
    }
}
