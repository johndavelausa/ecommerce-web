<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin');
        Role::findOrCreate('seller');
        Role::findOrCreate('customer');

        // Fix existing users without any role (e.g. created before roles existed)
        User::query()
            ->doesntHave('roles')
            ->each(fn (User $u) => $u->assignRole('customer'));
    }
}
