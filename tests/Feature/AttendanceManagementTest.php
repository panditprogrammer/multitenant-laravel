<?php

use App\Models\Attendance;
use App\Models\Library;
use App\Models\Membership;
use App\Models\Room;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('student can mark and unmark attendance from the dedicated attendance page', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $student = User::factory()->create(['role' => 'student']);

    $library = Library::create([
        'user_id' => $owner->id,
        'name' => 'Quiet Space Library',
        'city' => 'Indore',
        'normal_price' => 1200,
        'ac_price' => 1800,
    ]);

    $student->update(['library_id' => $library->id]);

    $room = Room::create([
        'library_id' => $library->id,
        'name' => 'Hall A',
        'type' => 'AC',
    ]);

    $seat = Seat::create([
        'room_id' => $room->id,
        'seat_number' => 'A-12',
    ]);

    Membership::create([
        'library_id' => $library->id,
        'user_id' => $student->id,
        'seat_id' => $seat->id,
        'shift_ids' => [],
        'start_date' => now()->subDays(3)->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'amount' => 1800,
        'status' => 'active',
    ]);

    $this->actingAs($student)->get(route('student.attendance'))
        ->assertOk()
        ->assertSee('Monthly View');

    Livewire::test('pages::student.attendance')
        ->call('toggleTodayAttendance')
        ->assertSet('month', now()->format('Y-m'))
        ->assertSee('Unmark Today Attendance');

    $this->assertDatabaseHas('attendances', [
        'user_id' => $student->id,
        'library_id' => $library->id,
        'room_id' => $room->id,
        'seat_id' => $seat->id,
        'attended_on' => today()->startOfDay()->toDateTimeString(),
    ]);

    Livewire::test('pages::student.attendance')
        ->call('toggleTodayAttendance')
        ->assertSet('month', now()->format('Y-m'))
        ->assertSee('Mark Today Attendance');

    $this->assertDatabaseMissing('attendances', [
        'user_id' => $student->id,
        'attended_on' => today()->startOfDay()->toDateTimeString(),
    ]);
});

test('owner attendance page only shows attendance for owned libraries', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $otherOwner = User::factory()->create(['role' => 'owner']);

    $ownerStudent = User::factory()->create(['role' => 'student']);
    $otherStudent = User::factory()->create(['role' => 'student']);

    $ownerLibrary = Library::create([
        'user_id' => $owner->id,
        'name' => 'Alpha Library',
        'city' => 'Indore',
        'normal_price' => 1000,
        'ac_price' => 1500,
    ]);

    $otherLibrary = Library::create([
        'user_id' => $otherOwner->id,
        'name' => 'Beta Library',
        'city' => 'Bhopal',
        'normal_price' => 900,
        'ac_price' => 1400,
    ]);

    $ownerStudent->update(['library_id' => $ownerLibrary->id]);
    $otherStudent->update(['library_id' => $otherLibrary->id]);

    $ownerRoom = Room::create([
        'library_id' => $ownerLibrary->id,
        'name' => 'Room A',
        'type' => 'AC',
    ]);

    $otherRoom = Room::create([
        'library_id' => $otherLibrary->id,
        'name' => 'Room B',
        'type' => 'NORMAL',
    ]);

    $ownerSeat = Seat::create([
        'room_id' => $ownerRoom->id,
        'seat_number' => 'A-1',
    ]);

    $otherSeat = Seat::create([
        'room_id' => $otherRoom->id,
        'seat_number' => 'B-9',
    ]);

    $ownerMembership = Membership::create([
        'library_id' => $ownerLibrary->id,
        'user_id' => $ownerStudent->id,
        'seat_id' => $ownerSeat->id,
        'shift_ids' => [],
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'amount' => 1500,
        'status' => 'active',
    ]);

    $otherMembership = Membership::create([
        'library_id' => $otherLibrary->id,
        'user_id' => $otherStudent->id,
        'seat_id' => $otherSeat->id,
        'shift_ids' => [],
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'amount' => 1400,
        'status' => 'active',
    ]);

    Attendance::create([
        'membership_id' => $ownerMembership->id,
        'library_id' => $ownerLibrary->id,
        'room_id' => $ownerRoom->id,
        'seat_id' => $ownerSeat->id,
        'user_id' => $ownerStudent->id,
        'attended_on' => today()->toDateString(),
    ]);

    Attendance::create([
        'membership_id' => $otherMembership->id,
        'library_id' => $otherLibrary->id,
        'room_id' => $otherRoom->id,
        'seat_id' => $otherSeat->id,
        'user_id' => $otherStudent->id,
        'attended_on' => today()->toDateString(),
    ]);

    $this->actingAs($owner)->get(route('owner.attendance'))
        ->assertOk()
        ->assertSee('Alpha Library')
        ->assertSee('Room A')
        ->assertSee('A-1')
        ->assertDontSee('Beta Library')
        ->assertDontSee('Room B')
        ->assertDontSee('B-9');

    Livewire::test('pages::attendance')
        ->assertSee('Alpha Library')
        ->assertSee('Room A')
        ->assertSee('A-1')
        ->assertDontSee('Beta Library')
        ->assertDontSee('Room B')
        ->assertDontSee('B-9');
});
