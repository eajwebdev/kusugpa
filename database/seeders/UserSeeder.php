<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed one demo user per role (defined in config/demo.php).
     * Safe to run repeatedly: users are matched by username.
     */
    public function run(): void
    {
        $password = config('demo.password');

        foreach (config('demo.accounts') as $account) {
            $user = User::updateOrCreate(
                ['username' => $account['username']],
                [
                    'fname'    => $account['fname'],
                    'mname'    => $account['mname'],
                    'lname'    => $account['lname'],
                    'role'     => $account['role'],
                    'password' => Hash::make($password),
                ]
            );

            $ids = $account['permissions'] === '*'
                ? Permission::pluck('id')
                : Permission::whereIn('slug', $account['permissions'])->pluck('id');

            $user->permissions()->sync($ids);
        }

        $this->command->info('Demo users seeded: ' . count(config('demo.accounts')));
        $this->command->info('Demo password for all accounts: ' . $password);
    }
}
