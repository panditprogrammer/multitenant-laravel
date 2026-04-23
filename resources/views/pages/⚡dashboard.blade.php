<?php

use App\Models\Library;
use App\Models\Membership;
use Livewire\Component;

new class extends Component {
    public $libraries;
    public $recentMemberships;
    public $expiringMemberships;
    public $stats = [];

    public function mount()
    {
        $this->libraries = Library::query()
            ->where('user_id', auth()->id())
            ->withCount(['rooms', 'students', 'seats', 'shifts'])
            ->latest()
            ->get();

        $libraryIds = $this->libraries->pluck('id');

        $activeMembershipsByLibrary = Membership::query()
            ->selectRaw('library_id, count(*) as total')
            ->whereIn('library_id', $libraryIds)
            ->where('status', 'active')
            ->whereDate('end_date', '>=', today())
            ->groupBy('library_id')
            ->pluck('total', 'library_id');

        $occupiedSeats = Membership::query()
            ->whereIn('library_id', $libraryIds)
            ->where('status', 'active')
            ->whereDate('end_date', '>=', today())
            ->distinct('seat_id')
            ->count('seat_id');

        $activeRevenue = Membership::query()
            ->whereIn('library_id', $libraryIds)
            ->where('status', 'active')
            ->whereDate('end_date', '>=', today())
            ->sum('amount');

        $this->recentMemberships = Membership::query()
            ->with(['user', 'seat.room', 'library', 'shifts'])
            ->whereIn('library_id', $libraryIds)
            ->latest()
            ->take(8)
            ->get();

        $this->expiringMemberships = Membership::query()
            ->with(['user', 'seat.room', 'library'])
            ->whereIn('library_id', $libraryIds)
            ->where('status', 'active')
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->copy()->addDays(3))
            ->orderBy('end_date')
            ->take(8)
            ->get();

        $this->libraries = $this->libraries->map(function ($library) use ($activeMembershipsByLibrary) {
            $library->active_memberships_count = $activeMembershipsByLibrary[$library->id] ?? 0;

            return $library;
        });

        $this->stats = [
            'libraries' => $this->libraries->count(),
            'students' => $this->libraries->sum('students_count'),
            'rooms' => $this->libraries->sum('rooms_count'),
            'seats' => $this->libraries->sum('seats_count'),
            'active_memberships' => $activeMembershipsByLibrary->sum(),
            'available_seats' => max($this->libraries->sum('seats_count') - $occupiedSeats, 0),
            'shifts' => $this->libraries->sum('shifts_count'),
            'active_revenue' => $activeRevenue,
        ];
    }
};
?>

<section>
    <div class="relative mb-6 w-full">
        <div>
            <flux:heading size="xl" level="1">{{ __('Owner Dashboard') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">
                {{ __('Track your libraries, students, seats, memberships, and revenue in one place') }}
            </flux:subheading>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Libraries') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $stats['libraries'] }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Total managed libraries') }}</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Students') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $stats['students'] }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Students assigned to your libraries') }}</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Active Memberships') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $stats['active_memberships'] }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Currently active and valid plans') }}</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Active Revenue') }}</flux:text>
                <flux:heading size="lg" class="mt-2">INR {{ number_format((float) $stats['active_revenue'], 2) }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Total from active memberships') }}</flux:text>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Rooms') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $stats['rooms'] }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Rooms created across libraries') }}</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Seats') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $stats['seats'] }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Total seat capacity') }}</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Available Seats') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $stats['available_seats'] }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Seats not occupied by active memberships') }}</flux:text>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="text-sm text-zinc-500">{{ __('Shifts') }}</flux:text>
                <flux:heading size="lg" class="mt-2">{{ $stats['shifts'] }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Shift plans configured') }}</flux:text>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Library Overview') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">
                                {{ __('Quick summary of each library under your account') }}
                            </flux:text>
                        </div>
                    </div>

                    <div class="mt-6">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Library') }}</flux:table.column>
                                <flux:table.column>{{ __('Students') }}</flux:table.column>
                                <flux:table.column>{{ __('Rooms') }}</flux:table.column>
                                <flux:table.column>{{ __('Seats') }}</flux:table.column>
                                <flux:table.column>{{ __('Shifts') }}</flux:table.column>
                                <flux:table.column>{{ __('Active Memberships') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($libraries as $library)
                                    <flux:table.row :key="$library->id">
                                        <flux:table.cell class="flex items-center gap-3">
                                            <flux:avatar size="xs" src="{{ $library->profile_image_url }}" />
                                            <div>
                                                <div class="font-medium">{{ $library->name }}</div>
                                                <div class="text-xs text-zinc-500">{{ $library->city ?: 'City not added' }}</div>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $library->students_count }}</flux:table.cell>
                                        <flux:table.cell>{{ $library->rooms_count }}</flux:table.cell>
                                        <flux:table.cell>{{ $library->seats_count }}</flux:table.cell>
                                        <flux:table.cell>{{ $library->shifts_count }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge color="{{ $library->active_memberships_count ? 'green' : 'zinc' }}">
                                                {{ $library->active_memberships_count }}
                                            </flux:badge>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                                            {{ __('No libraries found yet.') }}
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
                            <flux:heading size="lg">{{ __('Recent Memberships') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">
                                {{ __('Latest admissions and renewals across your libraries') }}
                            </flux:text>
                        </div>
                    </div>

                    <div class="mt-6">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('Student') }}</flux:table.column>
                                <flux:table.column>{{ __('Library') }}</flux:table.column>
                                <flux:table.column>{{ __('Seat') }}</flux:table.column>
                                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($recentMemberships as $membership)
                                    <flux:table.row :key="$membership->id">
                                        <flux:table.cell>{{ $membership->user?->name ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>{{ $membership->library?->name ?? '-' }}</flux:table.cell>
                                        <flux:table.cell>
                                            {{ $membership->seat?->seat_number ?? '-' }}
                                            @if ($membership->seat?->room)
                                                <div class="text-xs text-zinc-500">{{ $membership->seat->room->name }}</div>
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if ($membership->shifts->isNotEmpty())
                                                @foreach ($membership->shifts as $shift)
                                                    <div>{{ $shift->name }}</div>
                                                @endforeach
                                            @else
                                                -
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>INR {{ number_format((float) $membership->amount, 2) }}</flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="5" class="text-center text-zinc-500">
                                            {{ __('No memberships found yet.') }}
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
                    <flux:heading size="lg">{{ __('Expiring Soon') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        {{ __('Memberships ending within the next 3 days') }}
                    </flux:text>

                    <div class="mt-5 space-y-3">
                        @forelse ($expiringMemberships as $membership)
                            <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">
                                            {{ $membership->user?->name ?? '-' }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-zinc-500">
                                            {{ $membership->library?->name ?? '-' }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-zinc-500">
                                            {{ __('Ends on') }} {{ $membership->end_date?->format('d M Y') ?? '-' }}
                                        </flux:text>
                                    </div>

                                    <flux:badge color="amber">
                                        {{ $membership->seat?->seat_number ?? '-' }}
                                    </flux:badge>
                                </div>
                            </div>
                        @empty
                            <flux:text class="rounded-xl border border-dashed border-zinc-300 p-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-300">
                                {{ __('No memberships are expiring in the next 3 days.') }}
                            </flux:text>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Quick Actions') }}</flux:heading>

                    <div class="mt-4 space-y-3">
                        <flux:button class="w-full justify-center" :href="route('library.create')" wire:navigate>
                            {{ __('Create Library') }}
                        </flux:button>
                        <flux:button class="w-full justify-center" variant="outline" :href="route('room.manage')" wire:navigate>
                            {{ __('Manage Rooms') }}
                        </flux:button>
                        <flux:button class="w-full justify-center" variant="outline" :href="route('student.create')" wire:navigate>
                            {{ __('Create Student') }}
                        </flux:button>
                        <flux:button class="w-full justify-center" variant="outline" :href="route('student.manage')" wire:navigate>
                            {{ __('Manage Students') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
