<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('database seeder creates default owner permissions and assigns them to owner role', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = User::factory()->create(['role' => 'owner']);
    $ownerRole = Role::findByName('owner', 'web');

    expect($owner)->not->toBeNull();
    expect($owner->hasRole('owner'))->toBeTrue();
    expect($ownerRole->permissions->pluck('name')->all())->toContain(
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
    );
});

test('database seeder creates default student permissions and assigns them to student role', function () {
    $this->seed(DatabaseSeeder::class);

    $student = User::factory()->create(['role' => 'student']);
    $studentRole = Role::findByName('student', 'web');

    expect($student->hasRole('student'))->toBeTrue();
    expect($studentRole->permissions->pluck('name')->all())->toContain(
        'view_student_dashboard',
        'view_own_payments',
        'view_own_attendance',
        'create_membership_payment',
    );
});

test('owner can create a custom role with selected permissions', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = User::factory()->create(['role' => 'owner']);
    $this->actingAs($owner);

    Livewire::test('library::role.manage')
        ->set('name', 'Front Desk')
        ->set('selectedPermissions', ['view_student', 'create_student', 'view_payment'])
        ->call('saveRole')
        ->assertHasNoErrors();

    $role = Role::query()->where('owner_id', $owner->id)->where('name', 'Front Desk')->first();

    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('view_student'))->toBeTrue();
    expect($role->hasPermissionTo('create_student'))->toBeTrue();
    expect($role->hasPermissionTo('view_payment'))->toBeTrue();
});

test('owner can create a staff login with a custom role', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = User::factory()->create(['role' => 'owner']);

    $role = Role::create([
        'name' => 'Reception',
        'guard_name' => 'web',
        'owner_id' => $owner->id,
    ]);
    $role->givePermissionTo(['view_student', 'create_student']);

    $this->actingAs($owner);

    Livewire::test('library::user.manage')
        ->set('name', 'Reception User')
        ->set('email', 'reception@example.com')
        ->set('password', '12345678')
        ->set('selectedRoleId', (string) $role->id)
        ->call('saveUser')
        ->assertHasNoErrors();

    $staff = User::query()->where('email', 'reception@example.com')->first();

    expect($staff)->not->toBeNull();
    expect($staff->owner_id)->toBe($owner->id);
    expect($staff->hasRole('Reception'))->toBeTrue();
    expect($staff->can('view_student'))->toBeTrue();
    expect($staff->can('view_payment'))->toBeFalse();
});

test('staff login can only access routes allowed by assigned permissions', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = User::factory()->create(['role' => 'owner']);

    $role = Role::create([
        'name' => 'Admissions',
        'guard_name' => 'web',
        'owner_id' => $owner->id,
    ]);
    $role->givePermissionTo(['view_student', 'create_student']);

    $staff = User::create([
        'name' => 'Staff User',
        'email' => 'staff@example.com',
        'password' => '12345678',
        'role' => 'owner',
        'owner_id' => $owner->id,
    ]);
    $staff->assignRole($role);

    $this->actingAs($staff)->get(route('student.manage'))->assertOk();
    $this->actingAs($staff)->get(route('room.manage'))->assertForbidden();
    $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('owner.dashboard'));
});
