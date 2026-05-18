<?php

namespace Database\Seeders;

use App\Support\PermissionRegistry;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        PermissionRegistry::ensureDefaultRoles();
    }
}
