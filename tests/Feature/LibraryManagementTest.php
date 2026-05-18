<?php

use App\Models\Library;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

test('owner can create a library with opening and closing times', function () {
    Storage::fake('public');

    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::library.create')
        ->set('name', 'Sunrise Library')
        ->set('email', 'sunrise@example.com')
        ->set('phone', '9999999999')
        ->set('whatsapp', '9999999999')
        ->set('state', 'MP')
        ->set('city', 'Indore')
        ->set('address', 'MG Road')
        ->set('normal_price', 1200)
        ->set('ac_price', 1800)
        ->set('open_time', '06:30')
        ->set('close_time', '21:15')
        ->set('profile_image', UploadedFile::fake()->image('library.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $library = Library::query()->where('name', 'Sunrise Library')->firstOrFail();

    expect($library->open_time)->toBe('06:30:00');
    expect($library->close_time)->toBe('21:15:00');
});

test('owner can update a library and preserve editable time inputs', function () {
    Storage::fake('public');

    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $library = Library::create([
        'user_id' => $owner->id,
        'name' => 'Evening Library',
        'email' => 'evening@example.com',
        'phone' => '9999999998',
        'whatsapp' => '9999999998',
        'state' => 'MP',
        'city' => 'Bhopal',
        'address' => 'Main Road',
        'normal_price' => 900,
        'ac_price' => 1400,
        'open_time' => '07:00:00',
        'close_time' => '22:00:00',
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::library.create')
        ->call('edit', (string) $library->id)
        ->assertSet('open_time', '07:00')
        ->assertSet('close_time', '22:00')
        ->set('open_time', '08:15')
        ->set('close_time', '23:30')
        ->call('save')
        ->assertHasNoErrors();

    $library->refresh();

    expect($library->open_time)->toBe('08:15:00');
    expect($library->close_time)->toBe('23:30:00');
});

test('generated shifts stay within the library opening and closing time window', function () {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $library = Library::create([
        'user_id' => $owner->id,
        'name' => 'Schedule Library',
        'email' => 'schedule@example.com',
        'phone' => '9999999997',
        'whatsapp' => '9999999997',
        'state' => 'MP',
        'city' => 'Indore',
        'address' => 'Clock Tower',
        'normal_price' => 1000,
        'ac_price' => 1500,
        'open_time' => '06:30:00',
        'close_time' => '21:15:00',
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::library.create')
        ->call('openShiftModal', (string) $library->id)
        ->set('shift_count', 3)
        ->call('generateShifts')
        ->assertSet('shifts.0.start_time', '06:30')
        ->assertSet('shifts.2.end_time', '21:15')
        ->assertSet('shifts.0.end_time', '11:25')
        ->assertSet('shifts.1.start_time', '11:25')
        ->assertSet('shifts.1.end_time', '16:20')
        ->assertSet('shifts.2.start_time', '16:20')
        ->call('saveShifts')
        ->assertHasNoErrors();

    $savedShifts = Shift::query()
        ->where('library_id', $library->id)
        ->orderBy('id')
        ->get(['start_time', 'end_time'])
        ->toArray();

    expect($savedShifts)->toBe([
        ['start_time' => '06:30', 'end_time' => '11:25'],
        ['start_time' => '11:25', 'end_time' => '16:20'],
        ['start_time' => '16:20', 'end_time' => '21:15'],
    ]);
});
