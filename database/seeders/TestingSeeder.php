<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestingSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed roles and permissions.
        // All other test data is built via factories inside each test.
        $this->call([
            RolePermissionSeeder::class,
        ]);
    }
}
