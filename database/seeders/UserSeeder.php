<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Super Admin', 'email' => 'admin@sterling.test', 'role' => RoleSlug::SuperAdmin],
            ['name' => 'Bond Requester', 'email' => 'requester@sterling.test', 'role' => RoleSlug::Requester],
            ['name' => 'Data Encoder', 'email' => 'encoder@sterling.test', 'role' => RoleSlug::Encoder],
            ['name' => 'Bond Approver', 'email' => 'approver@sterling.test', 'role' => RoleSlug::Approver],
            ['name' => 'Notary Officer', 'email' => 'notary@sterling.test', 'role' => RoleSlug::Notary],
        ];

        foreach ($users as $data) {
            $role = Role::where('slug', $data['role']->value)->first();

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role_id' => $role?->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
