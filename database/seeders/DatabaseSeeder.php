<?php

namespace Database\Seeders;

use App\Support\PermissionRegistry;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // default permissions and roles
        $all_permissions = [
            'view_role',
            'create_role',
            'edit_role',
            'delete_role',
            'view_user',
            'create_user',
            'edit_user',
            'delete_user',
            'view_library',
            'edit_library',
            'view_library_shift',
            'edit_library_shift',
            'view_room',
            'create_room',
            'edit_room',
            'delete_room',
            'view_seat',
            'generate_seat',
            'view_student',
            'create_student',
            'edit_student',
            'delete_student',
            'view_membership',
            'edit_membership',
            'view_payment',
            'view_attendance',
        ];

        foreach ($all_permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $ownerRole = Role::findOrCreate('owner', 'web');
        $studentRole = Role::findOrCreate('student', 'web');

        $ownerRole->syncPermissions(Permission::all());
        $studentRole->syncPermissions([]);

        //  Super admin will be created later 
    }
}
