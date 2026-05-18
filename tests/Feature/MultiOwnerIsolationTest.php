<?php

use App\Models\Library;
use App\Models\Membership;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner student detail modal does not show memberships from another owner library', function () {
    $ownerA = User::factory()->create(['role' => 'owner']);
    $ownerB = User::factory()->create(['role' => 'owner']);
    $student = User::factory()->create(['role' => 'student']);

    $libraryA = Library::create([
        'user_id' => $ownerA->id,
        'name' => 'Alpha Library',
        'city' => 'Indore',
        'normal_price' => 1000,
        'ac_price' => 1500,
    ]);

    $libraryB = Library::create([
        'user_id' => $ownerB->id,
        'name' => 'Beta Library',
        'city' => 'Bhopal',
        'normal_price' => 900,
        'ac_price' => 1400,
    ]);

    $student->update(['library_id' => $libraryA->id]);

    $roomA = Room::create([
        'library_id' => $libraryA->id,
        'name' => 'Room A',
        'type' => 'AC',
    ]);

    $roomB = Room::create([
        'library_id' => $libraryB->id,
        'name' => 'Room B',
        'type' => 'NORMAL',
    ]);

    $seatA = Seat::create([
        'room_id' => $roomA->id,
        'seat_number' => 'A-1',
    ]);

    $seatB = Seat::create([
        'room_id' => $roomB->id,
        'seat_number' => 'B-9',
    ]);

    Membership::create([
        'library_id' => $libraryA->id,
        'user_id' => $student->id,
        'seat_id' => $seatA->id,
        'shift_ids' => [],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'amount' => 1500,
        'status' => 'active',
    ]);

    Membership::create([
        'library_id' => $libraryB->id,
        'user_id' => $student->id,
        'seat_id' => $seatB->id,
        'shift_ids' => [],
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->toDateString(),
        'amount' => 1400,
        'status' => 'expired',
    ]);

    $this->actingAs($ownerA);

    Livewire::test('library::student.manage')
        ->call('show', (string) $student->id)
        ->assertSee('Alpha Library')
        ->assertSee('A-1')
        ->assertDontSee('Beta Library')
        ->assertDontSee('B-9');
});

test('owner can admit an existing registered student who is not assigned to any library', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $registeredStudent = User::factory()->create([
        'role' => 'student',
        'library_id' => null,
        'email' => 'registered-student@example.com',
    ]);

    $library = Library::create([
        'user_id' => $owner->id,
        'name' => 'Gamma Library',
        'city' => 'Indore',
        'normal_price' => 1000,
        'ac_price' => 1500,
    ]);

    $room = Room::create([
        'library_id' => $library->id,
        'name' => 'Main Hall',
        'type' => 'AC',
    ]);

    $seat = Seat::create([
        'room_id' => $room->id,
        'seat_number' => 'G-4',
    ]);

    $shift = Shift::create([
        'library_id' => $library->id,
        'name' => 'Morning',
        'start_time' => '06:00',
        'end_time' => '10:00',
    ]);

    $this->actingAs($owner);

    Livewire::test('library::student.manage')
        ->set('admission_mode', 'existing')
        ->call('selectExistingStudent', (string) $registeredStudent->id)
        ->set('form_library_id', (string) $library->id)
        ->set('form_room_id', (string) $room->id)
        ->set('form_shift_ids', [(string) $shift->id])
        ->set('form_seat_id', (string) $seat->id)
        ->set('amount', 1500)
        ->call('saveStudent')
        ->assertHasNoErrors();

    $registeredStudent->refresh();

    expect($registeredStudent->library_id)->toBe($library->id);
    expect(User::query()->where('email', 'registered-student@example.com')->count())->toBe(1);

    $membership = Membership::query()->where('user_id', $registeredStudent->id)->first();

    expect($membership)->not->toBeNull();
    expect($membership->library_id)->toBe($library->id);
    expect($membership->seat_id)->toBe($seat->id);
});
