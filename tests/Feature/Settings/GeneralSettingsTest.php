<?php

use App\Livewire\Settings\General;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner general settings page is displayed', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    $this->actingAs($owner)
        ->get(route('setup.payment-gateway.edit'))
        ->assertOk()
        ->assertSee('Setup &amp; Configurations', false)
        ->assertSee('Payment Gateway Settings')
        ->assertSee(route('payments.webhooks.razorpay'));
});

test('owner can update general payment gateway settings', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    $this->actingAs($owner);

    $response = Livewire::withQueryParams([])->test(General::class)
        ->set('razorpay_key_id', 'rzp_test_owner_key')
        ->set('razorpay_key_secret', 'owner_secret_123')
        ->set('razorpay_webhook_secret', 'owner_webhook_123')
        ->call('save');

    $response->assertHasNoErrors();

    $owner->refresh();

    expect($owner->razorpay_key_id)->toBe('rzp_test_owner_key');
    expect($owner->razorpay_key_secret)->toBe('owner_secret_123');
    expect($owner->razorpay_webhook_secret)->toBe('owner_webhook_123');
});

test('student cannot access owner general settings page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get(route('setup.payment-gateway.edit'))
        ->assertForbidden();
});
