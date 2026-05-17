<?php

use App\Models\Library;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function createMembershipForStudent(User $owner, User $student): Membership
{
    $library = Library::create([
        'user_id' => $owner->id,
        'name' => 'Scholars Library',
        'email' => 'scholars@example.com',
        'phone' => '9876543210',
        'city' => 'Indore',
        'normal_price' => 1000,
        'ac_price' => 1400,
    ]);

    $student->update([
        'library_id' => $library->id,
    ]);

    $room = Room::create([
        'library_id' => $library->id,
        'name' => 'Focus Hall',
        'type' => 'AC',
    ]);

    $seat = Seat::create([
        'room_id' => $room->id,
        'seat_number' => 'F-9',
    ]);

    return Membership::create([
        'library_id' => $library->id,
        'user_id' => $student->id,
        'seat_id' => $seat->id,
        'shift_ids' => [],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'amount' => 1400,
        'status' => 'active',
    ]);
}

test('student can create a razorpay order for an unpaid membership', function () {
    config()->set('services.razorpay.key_id', 'rzp_test_key');
    config()->set('services.razorpay.key_secret', 'rzp_test_secret');

    Http::fake([
        'https://api.razorpay.com/v1/orders' => Http::response([
            'id' => 'order_test_123',
            'entity' => 'order',
            'amount' => 140000,
            'currency' => 'INR',
            'receipt' => 'membership-1-123456',
            'status' => 'created',
        ], 200),
    ]);

    $owner = User::factory()->create(['role' => 'owner']);
    $student = User::factory()->create(['role' => 'student']);
    $membership = createMembershipForStudent($owner, $student);

    $response = $this->actingAs($student)
        ->postJson(route('student.memberships.payments.razorpay.order', $membership));

    $response->assertOk()
        ->assertJsonPath('key', 'rzp_test_key')
        ->assertJsonPath('payment.order_id', 'order_test_123');

    $this->assertDatabaseHas('payments', [
        'membership_id' => $membership->id,
        'user_id' => $student->id,
        'payment_method' => 'razorpay',
        'status' => 'created',
        'razorpay_order_id' => 'order_test_123',
    ]);
});

test('razorpay webhook verifies payment and updates membership status', function () {
    config()->set('services.razorpay.webhook_secret', 'whsec_test_123');

    $owner = User::factory()->create(['role' => 'owner']);
    $student = User::factory()->create(['role' => 'student']);
    $membership = createMembershipForStudent($owner, $student);

    $payment = Payment::create([
        'membership_id' => $membership->id,
        'library_id' => $membership->library_id,
        'user_id' => $student->id,
        'amount' => $membership->amount,
        'currency' => 'INR',
        'payment_method' => 'razorpay',
        'status' => 'created',
        'reference' => 'membership-webhook',
        'razorpay_order_id' => 'order_test_456',
    ]);

    $payload = json_encode([
        'event' => 'payment.captured',
        'payload' => [
            'payment' => [
                'entity' => [
                    'id' => 'pay_test_456',
                    'order_id' => 'order_test_456',
                    'amount' => 140000,
                    'currency' => 'INR',
                    'status' => 'captured',
                    'created_at' => now()->timestamp,
                    'notes' => [
                        'membership_id' => (string) $membership->id,
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $signature = hash_hmac('sha256', $payload, 'whsec_test_123');

    $response = $this->call(
        'POST',
        route('payments.webhooks.razorpay'),
        [],
        [],
        [],
        [
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    );

    $response->assertOk();

    expect($payment->fresh()->status)->toBe('paid');
    expect($payment->fresh()->razorpay_payment_id)->toBe('pay_test_456');
    expect($membership->fresh()->payment_method)->toBe('razorpay');
    expect($membership->fresh()->paid_at)->not->toBeNull();
});
