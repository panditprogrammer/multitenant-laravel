<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionRegistry
{
    public static function ownerPermissions(): array
    {
        return [
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
    }

    public static function studentPermissions(): array
    {
        return [
            'view_student_dashboard',
            'view_own_payments',
            'view_own_attendance',
            'create_membership_payment',
        ];
    }

    public static function defaultPermissions(): array
    {
        return [
            ...self::ownerPermissions(),
            ...self::studentPermissions(),
        ];
    }

    public static function ensureDefaultRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::defaultPermissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $ownerRole = Role::findOrCreate('owner', 'web');
        $studentRole = Role::findOrCreate('student', 'web');

        $ownerRole->syncPermissions(self::ownerPermissions());
        $studentRole->syncPermissions(self::studentPermissions());
    }
}
