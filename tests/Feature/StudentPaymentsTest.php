<?php

use App\Models\Library;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('students can view only their own payment history page', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $student = User::factory()->create(['role' => 'student']);
    $otherStudent = User::factory()->create(['role' => 'student']);

    $library = Library::create([
        'user_id' => $owner->id,
        'name' => 'Focus Library',
        'city' => 'Indore',
        'normal_price' => 1000,
        'ac_price' => 1600,
    ]);

    $student->update(['library_id' => $library->id]);
    $otherStudent->update(['library_id' => $library->id]);

    $room = Room::create([
        'library_id' => $library->id,
        'name' => 'Main Hall',
        'type' => 'AC',
    ]);

    $seatOne = Seat::create([
        'room_id' => $room->id,
        'seat_number' => 'A-1',
    ]);

    $seatTwo = Seat::create([
        'room_id' => $room->id,
        'seat_number' => 'A-2',
    ]);

    $membership = Membership::create([
        'library_id' => $library->id,
        'user_id' => $student->id,
        'seat_id' => $seatOne->id,
        'shift_ids' => [],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'amount' => 1600,
        'status' => 'active',
    ]);

    $otherMembership = Membership::create([
        'library_id' => $library->id,
        'user_id' => $otherStudent->id,
        'seat_id' => $seatTwo->id,
        'shift_ids' => [],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'amount' => 1600,
        'status' => 'active',
    ]);

    Payment::create([
        'membership_id' => $membership->id,
        'library_id' => $library->id,
        'user_id' => $student->id,
        'amount' => 1600,
        'currency' => 'INR',
        'payment_method' => 'razorpay',
        'status' => 'paid',
        'reference' => 'RP-SELF-001',
        'razorpay_payment_id' => 'pay_self_001',
        'paid_at' => now(),
        'verified_at' => now(),
    ]);

    Payment::create([
        'membership_id' => $otherMembership->id,
        'library_id' => $library->id,
        'user_id' => $otherStudent->id,
        'amount' => 1600,
        'currency' => 'INR',
        'payment_method' => 'cash',
        'status' => 'paid',
        'reference' => 'CASH-OTHER-001',
        'paid_at' => now(),
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($student)->get(route('student.payments'));

    $response->assertOk();
    $response->assertSee('My Payments');
    $response->assertSee('Focus Library');
    $response->assertSee('pay_self_001');
    $response->assertSee('Paid');
    $response->assertDontSee('CASH-OTHER-001');
    $response->assertDontSee('A-2');
});
