<?php

use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owners can view only their own libraries on the library management page', function () {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $otherOwner = User::factory()->create([
        'role' => 'owner',
    ]);

    Library::create([
        'user_id' => $owner->id,
        'name' => 'Alpha Library',
        'email' => 'alpha@example.com',
        'phone' => '9999999991',
        'whatsapp' => '9999999991',
        'state' => 'MP',
        'city' => 'Indore',
        'address' => 'Alpha Address',
        'normal_price' => 1000,
        'ac_price' => 1500,
    ]);

    Library::create([
        'user_id' => $otherOwner->id,
        'name' => 'Beta Library',
        'email' => 'beta@example.com',
        'phone' => '9999999992',
        'whatsapp' => '9999999992',
        'state' => 'MP',
        'city' => 'Bhopal',
        'address' => 'Beta Address',
        'normal_price' => 900,
        'ac_price' => 1400,
    ]);

    $response = $this->actingAs($owner)->get(route('library.create'));

    $response->assertOk();
    $response->assertSee('Manage Libraries');
    $response->assertSee('Alpha Library');
    $response->assertDontSee('Beta Library');
});
