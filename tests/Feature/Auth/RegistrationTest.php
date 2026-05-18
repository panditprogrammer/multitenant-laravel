<?php

use App\Support\PermissionRegistry;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'role' => 'student',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->role)->toBe('student');
    expect($user->hasRole('student'))->toBeTrue();
    expect($user->can('view_student_dashboard'))->toBeTrue();
});

test('owners receive default owner permissions during registration', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Library Owner',
        'email' => 'owner@example.com',
        'role' => 'owner',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($user->role)->toBe('owner');
    expect($user->hasRole('owner'))->toBeTrue();
    expect($user->getAllPermissions()->pluck('name')->all())
        ->toMatchArray(PermissionRegistry::ownerPermissions());
});
