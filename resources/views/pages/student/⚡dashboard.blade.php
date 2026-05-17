<?php

use App\Models\Membership;
use App\Models\Shift;
use Livewire\Component;

new class extends Component {

    public $student;
    public $library;
    public $currentMembership;
    public $upcomingRenewal;
    public $membershipHistory;
    public $paymentHistory;
    public $schedule;
    public $razorpayEnabled = false;


    public function mount()
    {
        $user = auth()
            ->user()
            ->load([
                'library.owner',
                'memberships' => fn($query) => $query->with(['seat.room.library', 'shifts', 'latestPayment'])->latest(),
                'payments' => fn($query) => $query->with(['membership.seat.room.library'])->latest(),
            ]);

        $currentMembership = $user->memberships->first(
            fn(Membership $membership) => $membership->status === 'active' && !$membership->end_date?->isPast()
        );

        $upcomingRenewal = $user->memberships
            ->filter(fn(Membership $membership) => !$membership->end_date?->isPast())
            ->sortBy('end_date')
            ->first();

        $schedule = collect();

        if ($currentMembership) {
            $schedule = $currentMembership->shifts;

            if ($schedule->isEmpty()) {
                $schedule = Shift::query()
                    ->whereIn('id', $currentMembership->shift_ids ?? [])
                    ->orderBy('start_time')
                    ->get();
            }
        }

        $this->student = $user;
        $this->library = $user->library;
        $this->currentMembership = $currentMembership;
        $this->upcomingRenewal = $upcomingRenewal;
        $this->membershipHistory = $user->memberships;
        $this->paymentHistory = $user->payments;
        $this->schedule = $schedule;
        $this->razorpayEnabled = filled($currentMembership?->library?->owner?->razorpay_key_id);
    }
};
?>

<section>
    <div class="relative mb-6 w-full">
        <div>
            <flux:heading size="xl" level="1">{{ __('Student Dashboard') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">
                {{ __('View your library, seat, membership, and shift details') }}
            </flux:subheading>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Student') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $student?->name }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ $student?->email }}</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Library') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $library?->name ?? 'Not assigned yet' }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ $library?->city ?: 'City not added' }}</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Current Seat') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $currentMembership?->seat?->seat_number ?? 'Not assigned' }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ $currentMembership?->seat?->room?->name ?? 'Choose a room from active membership' }}
                </flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Monthly Amount') }}</flux:text>
                <flux:heading size="lg" class="mt-2">
                    {{ $currentMembership ? 'INR ' . number_format((float) $currentMembership->amount, 2) : 'INR 0.00' }}
                </flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ $currentMembership ? ucfirst($currentMembership->status) : 'Inactive' }}
                </flux:text>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Membership Overview') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">
                                {{ __('Your latest admission and renewal details') }}
                            </flux:text>
                        </div>

                        @if ($currentMembership)
                            <flux:badge color="{{ $currentMembership->status === 'active' ? 'green' : 'zinc' }}">
                                {{ ucfirst($currentMembership->status) }}
                            </flux:badge>
                        @endif
                    </div>

                    @if ($currentMembership)
                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                                <flux:text class="text-sm text-zinc-500">{{ __('Period') }}</flux:text>
                                <flux:text class="mt-2 font-medium text-zinc-800 dark:text-zinc-100">
                                    {{ $currentMembership->start_date?->format('d M Y') }} to {{ $currentMembership->end_date?->format('d M Y') }}
                                </flux:text>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                                <flux:text class="text-sm text-zinc-500">{{ __('Renewal') }}</flux:text>
                                <flux:text class="mt-2 font-medium text-zinc-800 dark:text-zinc-100">
                                    {{ $upcomingRenewal?->end_date ? 'Valid until ' . $upcomingRenewal->end_date->format('d M Y') : 'No active membership right now' }}
                                </flux:text>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                                <flux:text class="text-sm text-zinc-500">{{ __('Seat & Room') }}</flux:text>
                                <flux:text class="mt-2 font-medium text-zinc-800 dark:text-zinc-100">
                                    {{ $currentMembership->seat?->seat_number ?? 'N/A' }}
                                    @if ($currentMembership->seat?->room)
                                        in {{ $currentMembership->seat->room->name }}
                                    @endif
                                </flux:text>
                                <flux:text class="mt-1 text-sm text-zinc-500">
                                    {{ $currentMembership->seat?->room?->type ?? 'N/A' }}
                                    @if ($currentMembership?->seat?->room?->floor)
                                        | {{ $currentMembership->seat->room->floor }}
                                    @endif
                                </flux:text>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                                <flux:text class="text-sm text-zinc-500">{{ __('Owner Contact') }}</flux:text>
                                <flux:text class="mt-2 font-medium text-zinc-800 dark:text-zinc-100">
                                    {{ $library?->owner?->name ?? 'Owner not available' }}
                                </flux:text>
                                <flux:text class="mt-1 text-sm text-zinc-500">
                                    {{ $library?->phone ?: ($library?->email ?: 'No contact added') }}
                                </flux:text>
                            </div>

                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80 md:col-span-2">
                                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <flux:text class="text-sm text-zinc-500">{{ __('Payment Status') }}</flux:text>
                                        <flux:text class="mt-2 font-medium text-zinc-800 dark:text-zinc-100">
                                            @if ($currentMembership->paid_at)
                                                {{ $currentMembership->payment_method === 'razorpay' ? __('Paid Online') : __('Paid by Cash') }}
                                            @else
                                                {{ __('Pending') }}
                                            @endif
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-zinc-500">
                                            @if ($currentMembership->paid_at)
                                                {{ __('Verified on') }} {{ $currentMembership->paid_at->format('d M Y h:i A') }}
                                            @else
                                                {{ __('You can pay this membership fee online or visit the owner for cash collection.') }}
                                            @endif
                                        </flux:text>
                                    </div>

                                    @if (!$currentMembership->paid_at && $razorpayEnabled)
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                                            data-razorpay-pay
                                            data-membership-id="{{ $currentMembership->id }}"
                                            data-order-url="{{ route('student.memberships.payments.razorpay.order', $currentMembership) }}"
                                        >
                                            {{ __('Pay Online with Razorpay') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-6">
                            <flux:text class="rounded-xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-300">
                                {{ __('No active membership found. Once a seat is allotted, the latest details will appear here.') }}
                            </flux:text>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Membership History') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">
                                {{ __('Recent records from your student account') }}
                            </flux:text>
                        </div>

                        <flux:badge color="zinc">{{ $membershipHistory?->count() ?? 0 }}</flux:badge>
                    </div>

                    <div class="mt-6">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Library') }}</flux:table.column>
                                <flux:table.column>{{ __('Seat') }}</flux:table.column>
                                <flux:table.column>{{ __('Room') }}</flux:table.column>
                                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                                <flux:table.column>{{ __('Period') }}</flux:table.column>
                                <flux:table.column>{{ __('Status') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($membershipHistory as $membership)
                                    <flux:table.row :key="$membership->id">
                                        <flux:table.cell>{{ $membership->seat?->room?->library?->name ?? $library?->name ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>{{ $membership->seat?->seat_number ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>{{ $membership->seat?->room?->name ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>INR {{ number_format((float) $membership->amount, 2) }}</flux:table.cell>
                                        <flux:table.cell>
                                            {{ $membership->start_date?->format('d M Y') ?? '-' }} to {{ $membership->end_date?->format('d M Y') ?? '-' }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge color="{{ $membership->status === 'active' ? 'green' : 'zinc' }}">
                                                {{ ucfirst($membership->status) }}
                                            </flux:badge>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                                            {{ __('No membership history available yet.') }}
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Payment History') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">
                                {{ __('Cash and online payments recorded for your memberships') }}
                            </flux:text>
                        </div>

                        <flux:badge color="zinc">{{ $paymentHistory?->count() ?? 0 }}</flux:badge>
                    </div>

                    <div class="mt-6">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Library') }}</flux:table.column>
                                <flux:table.column>{{ __('Method') }}</flux:table.column>
                                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                                <flux:table.column>{{ __('Status') }}</flux:table.column>
                                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                                <flux:table.column>{{ __('Date') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($paymentHistory as $payment)
                                    <flux:table.row :key="$payment->id">
                                        <flux:table.cell>{{ $payment->membership?->seat?->room?->library?->name ?? $library?->name ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>{{ ucfirst($payment->payment_method ?? 'pending') }}</flux:table.cell>
                                        <flux:table.cell>INR {{ number_format((float) $payment->amount, 2) }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge color="{{ in_array($payment->status, ['paid', 'captured'], true) ? 'green' : (in_array($payment->status, ['failed'], true) ? 'red' : 'amber') }}">
                                                {{ ucfirst($payment->status) }}
                                            </flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $payment->razorpay_payment_id ?? $payment->reference ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>{{ ($payment->paid_at ?? $payment->created_at)?->format('d M Y h:i A') ?? '-' }}</flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                                            {{ __('No payment history available yet.') }}
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Shift Schedule') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        {{ __('Pulled from your current membership') }}
                    </flux:text>

                    <div class="mt-5 space-y-3">
                        @forelse ($schedule as $shift)
                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                                <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ $shift->name }}</flux:text>
                                <flux:text class="mt-1 text-sm text-zinc-500">
                                    {{ $shift->start_time?->format('h:i A') ?? $shift->start_time }}
                                    to
                                    {{ $shift->end_time?->format('h:i A') ?? $shift->end_time }}
                                </flux:text>
                            </div>
                        @empty
                            <flux:text class="rounded-xl border border-dashed border-zinc-300 p-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-300">
                                {{ __('No shift schedule is linked to the current membership yet.') }}
                            </flux:text>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Library Contact') }}</flux:heading>
                    <div class="mt-4 space-y-3">
                        <div>
                            <flux:text class="text-sm text-zinc-500">{{ __('Phone') }}</flux:text>
                            <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ $library?->phone ?? '-' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-sm text-zinc-500">{{ __('Email') }}</flux:text>
                            <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ $library?->email ?? '-' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-sm text-zinc-500">{{ __('Address') }}</flux:text>
                            <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ $library?->address ?? '-' }}</flux:text>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($razorpayEnabled)
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            document.addEventListener('click', async function (event) {
                const trigger = event.target.closest('[data-razorpay-pay]');

                if (!trigger) {
                    return;
                }

                event.preventDefault();
                trigger.disabled = true;

                try {
                    const response = await fetch(trigger.dataset.orderUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({}),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to create Razorpay order.');
                    }

                    const checkout = new window.Razorpay({
                        key: data.key,
                        amount: data.payment.amount,
                        currency: data.payment.currency,
                        name: data.payment.name,
                        description: data.payment.description,
                        order_id: data.payment.order_id,
                        prefill: data.payment.prefill,
                        handler: function () {
                            window.alert('Payment submitted successfully. It will appear as paid after the Razorpay webhook verifies it.');
                            window.setTimeout(function () {
                                window.location.reload();
                            }, 1500);
                        },
                        modal: {
                            ondismiss: function () {
                                trigger.disabled = false;
                            }
                        }
                    });

                    checkout.open();
                } catch (error) {
                    window.alert(error.message || 'Unable to start payment.');
                    trigger.disabled = false;
                }
            });
        </script>
    @endif
</section>
