<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * The application's role set. Idempotent, so both the baseline and demo
     * seeders can call it without clashing.
     */
    public function run(): void
    {
        foreach (['admin', 'coach', 'parent', 'super_admin'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
