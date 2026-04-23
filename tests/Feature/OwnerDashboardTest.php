<?php

use App\Models\Library;
use App\Models\Membership;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owners can view their dashboard data', function () {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $student = User::factory()->create([
        'role' => 'student',
    ]);

    $library = Library::create([
        'user_id' => $owner->id,
        'name' => 'Prime Library',
        'city' => 'Bhopal',
        'normal_price' => 1000,
        'ac_price' => 1500,
    ]);

    $student->update([
        'library_id' => $library->id,
    ]);

    $room = Room::create([
        'library_id' => $library->id,
        'name' => 'Reading Hall',
        'type' => 'AC',
    ]);

    $seat = Seat::create([
        'room_id' => $room->id,
        'seat_number' => 'B-7',
    ]);

    $shift = Shift::create([
        'library_id' => $library->id,
        'name' => 'Evening',
        'start_time' => '16:00:00',
        'end_time' => '20:00:00',
    ]);

    $membership = Membership::create([
        'library_id' => $library->id,
        'user_id' => $student->id,
        'seat_id' => $seat->id,
        'shift_ids' => [$shift->id],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
        'amount' => 1500,
        'status' => 'active',
    ]);

    $membership->shifts()->sync([$shift->id]);

    $response = $this->actingAs($owner)->get(route('owner.dashboard'));

    $response->assertOk();
    $response->assertSee('Owner Dashboard');
    $response->assertSee('Prime Library');
    $response->assertSee('Reading Hall');
    $response->assertSee('Evening');
    $response->assertSee('1,500.00');
});

test('dashboard route redirects owners to owner dashboard', function () {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $response = $this->actingAs($owner)->get(route('dashboard'));

    $response->assertRedirect(route('owner.dashboard'));
});
