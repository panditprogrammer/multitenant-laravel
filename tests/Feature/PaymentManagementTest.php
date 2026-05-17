<?php

use App\Models\Library;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owners can view only their own payments on the payments management page', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $otherOwner = User::factory()->create(['role' => 'owner']);
    $student = User::factory()->create(['role' => 'student']);
    $otherStudent = User::factory()->create(['role' => 'student']);

    $library = Library::create([
        'user_id' => $owner->id,
        'name' => 'North Library',
        'city' => 'Indore',
        'normal_price' => 1000,
        'ac_price' => 1500,
    ]);

    $otherLibrary = Library::create([
        'user_id' => $otherOwner->id,
        'name' => 'South Library',
        'city' => 'Bhopal',
        'normal_price' => 900,
        'ac_price' => 1400,
    ]);

    $student->update(['library_id' => $library->id]);
    $otherStudent->update(['library_id' => $otherLibrary->id]);

    $room = Room::create([
        'library_id' => $library->id,
        'name' => 'Room A',
        'type' => 'AC',
    ]);

    $otherRoom = Room::create([
        'library_id' => $otherLibrary->id,
        'name' => 'Room B',
        'type' => 'NORMAL',
    ]);

    $seat = Seat::create([
        'room_id' => $room->id,
        'seat_number' => 'N-1',
    ]);

    $otherSeat = Seat::create([
        'room_id' => $otherRoom->id,
        'seat_number' => 'S-1',
    ]);

    $membership = Membership::create([
        'library_id' => $library->id,
        'user_id' => $student->id,
        'seat_id' => $seat->id,
        'shift_ids' => [],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'amount' => 1500,
        'status' => 'active',
    ]);

    $otherMembership = Membership::create([
        'library_id' => $otherLibrary->id,
        'user_id' => $otherStudent->id,
        'seat_id' => $otherSeat->id,
        'shift_ids' => [],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'amount' => 1400,
        'status' => 'active',
    ]);

    Payment::create([
        'membership_id' => $membership->id,
        'library_id' => $library->id,
        'user_id' => $student->id,
        'amount' => 1500,
        'currency' => 'INR',
        'payment_method' => 'cash',
        'status' => 'paid',
        'reference' => 'CASH-100001',
        'paid_at' => now(),
        'verified_at' => now(),
    ]);

    Payment::create([
        'membership_id' => $otherMembership->id,
        'library_id' => $otherLibrary->id,
        'user_id' => $otherStudent->id,
        'amount' => 1400,
        'currency' => 'INR',
        'payment_method' => 'razorpay',
        'status' => 'paid',
        'reference' => 'RP-100002',
        'razorpay_payment_id' => 'pay_hidden_002',
        'paid_at' => now(),
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get(route('payment.manage'));

    $response->assertOk();
    $response->assertSee('Payments');
    $response->assertSee('North Library');
    $response->assertSee('CASH-100001');
    $response->assertDontSee('South Library');
    $response->assertDontSee('pay_hidden_002');
});
