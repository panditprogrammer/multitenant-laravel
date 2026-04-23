<?php

use App\Models\Library;
use App\Models\Membership;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('students can view their dashboard data', function () {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $student = User::factory()->create([
        'role' => 'student',
        'library_id' => null,
    ]);

    $library = Library::create([
        'user_id' => $owner->id,
        'name' => 'Quiet Space Library',
        'email' => 'library@example.com',
        'phone' => '9999999999',
        'city' => 'Indore',
        'normal_price' => 1200,
        'ac_price' => 1800,
    ]);

    $student->update([
        'library_id' => $library->id,
    ]);

    $room = Room::create([
        'library_id' => $library->id,
        'name' => 'Hall A',
        'floor' => '1st Floor',
        'type' => 'AC',
    ]);

    $seat = Seat::create([
        'room_id' => $room->id,
        'seat_number' => 'A-12',
    ]);

    $shift = Shift::create([
        'library_id' => $library->id,
        'name' => 'Morning',
        'start_time' => '06:00:00',
        'end_time' => '10:00:00',
    ]);

    $membership = Membership::create([
        'library_id' => $library->id,
        'user_id' => $student->id,
        'seat_id' => $seat->id,
        'shift_ids' => [$shift->id],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'amount' => 1800,
        'status' => 'active',
    ]);

    $membership->shifts()->sync([$shift->id]);

    $response = $this->actingAs($student)->get(route('student.dashboard'));

    $response->assertOk();
    $response->assertSee('Quiet Space Library');
    $response->assertSee('A-12');
    $response->assertSee('Hall A');
    $response->assertSee('Morning');
    $response->assertSee('1,800.00');
});

test('dashboard route redirects students to the student dashboard', function () {
    $student = User::factory()->create([
        'role' => 'student',
    ]);

    $response = $this->actingAs($student)->get(route('dashboard'));

    $response->assertRedirect(route('student.dashboard'));
});
