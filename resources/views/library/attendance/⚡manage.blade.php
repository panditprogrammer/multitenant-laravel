<?php

use App\Models\Attendance;
use App\Models\Library;
use App\Models\Membership;
use App\Models\Room;
use App\Models\Seat;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $filter_library_id = '';

    public string $filter_room_id = '';

    public string $filter_seat_id = '';

    public string $month = '';

    protected function ownerId(): int
    {
        return auth()->user()->ownerAccountId();
    }

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updatedFilterLibraryId(): void
    {
        $this->filter_room_id = '';
        $this->filter_seat_id = '';
    }

    public function updatedFilterRoomId(): void
    {
        $this->filter_seat_id = '';
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    #[Computed]
    public function libraries()
    {
        return Library::query()
            ->where('user_id', $this->ownerId())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function rooms()
    {
        return Room::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function seats()
    {
        return Seat::query()
            ->whereHas('room.library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->when($this->filter_library_id, fn ($query) => $query->whereHas('room', fn ($roomQuery) => $roomQuery->where('library_id', $this->filter_library_id)))
            ->when($this->filter_room_id, fn ($query) => $query->where('room_id', $this->filter_room_id))
            ->orderBy('seat_number')
            ->get();
    }

    #[Computed]
    public function seatAttendance()
    {
        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $memberships = Membership::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->with([
                'user',
                'library',
                'seat.room',
                'attendances' => fn ($query) => $query
                    ->whereBetween('attended_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->orderBy('attended_on'),
            ])
            ->whereIn('status', ['active', 'expired'])
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
            ->when($this->filter_room_id, fn ($query) => $query->whereHas('seat', fn ($seatQuery) => $seatQuery->where('room_id', $this->filter_room_id)))
            ->when($this->filter_seat_id, fn ($query) => $query->where('seat_id', $this->filter_seat_id))
            ->orderBy('library_id')
            ->get();

        return $memberships->map(function (Membership $membership) use ($monthStart, $monthEnd) {
            $markedDates = $membership->attendances
                ->pluck('attended_on')
                ->filter()
                ->map(fn ($date) => $date->toDateString())
                ->values();

            $trackedStart = $membership->start_date && $membership->start_date->greaterThan($monthStart)
                ? $membership->start_date->copy()
                : $monthStart->copy();

            $trackedEnd = $membership->end_date && $membership->end_date->lessThan($monthEnd)
                ? $membership->end_date->copy()
                : $monthEnd->copy();

            $trackedDays = $trackedStart->greaterThan($trackedEnd)
                ? 0
                : $trackedStart->diffInDays($trackedEnd) + 1;

            return [
                'id' => $membership->id,
                'library_id' => $membership->library_id,
                'library' => $membership->library?->name ?? '-',
                'room_id' => $membership->seat?->room?->id,
                'room' => $membership->seat?->room?->name ?? '-',
                'seat_id' => $membership->seat_id,
                'seat' => $membership->seat?->seat_number ?? '-',
                'student' => $membership->user?->name ?? '-',
                'student_email' => $membership->user?->email ?? '-',
                'attendance_count' => $markedDates->count(),
                'tracked_days' => $trackedDays,
                'marked_dates' => $markedDates->all(),
                'marked_day_numbers' => $markedDates->map(fn ($date) => Carbon::parse($date)->day)->all(),
                'last_marked_at' => $membership->attendances->last()?->attended_on?->format('d M Y'),
            ];
        });
    }

    #[Computed]
    public function attendanceStats(): array
    {
        return [
            'libraries' => $this->seatAttendance->pluck('library_id')->filter()->unique()->count(),
            'rooms' => $this->seatAttendance->pluck('room_id')->filter()->unique()->count(),
            'seats' => $this->seatAttendance->count(),
            'marked_today' => Attendance::query()
                ->whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
                ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
                ->when($this->filter_room_id, fn ($query) => $query->where('room_id', $this->filter_room_id))
                ->when($this->filter_seat_id, fn ($query) => $query->where('seat_id', $this->filter_seat_id))
                ->whereDate('attended_on', today())
                ->count(),
        ];
    }

    #[Computed]
    public function monthLabel(): string
    {
        return $this->monthStart()->format('F Y');
    }

    #[Computed]
    public function monthDays(): array
    {
        return range(1, $this->monthStart()->copy()->endOfMonth()->day);
    }

    protected function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
    }
};
?>

<section class="space-y-8">
    <div class="relative w-full">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Attendance') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">
                    {{ __('Review student attendance by library, room, and seat') }}
                </flux:subheading>
            </div>

            <div class="flex items-center gap-3">
                <flux:button variant="outline" wire:click="previousMonth">{{ __('Previous') }}</flux:button>
                <div class="min-w-36 text-center text-sm font-medium">{{ $this->monthLabel }}</div>
                <flux:button variant="outline" wire:click="nextMonth">{{ __('Next') }}</flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Libraries in View') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->attendanceStats['libraries'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Libraries covered by the current filters') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Rooms in View') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->attendanceStats['rooms'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Rooms that currently match the filter') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Seats Tracked') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->attendanceStats['seats'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Seat assignments visible in the selected month') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Marked Today') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->attendanceStats['marked_today'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Attendance records captured for today') }}</flux:text>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div>
            <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Narrow the attendance register from library to room to seat.') }}</flux:text>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <flux:select wire:model.live="filter_library_id" label="Library">
                <flux:select.option value="">{{ __('All Libraries') }}</flux:select.option>
                @foreach ($this->libraries as $library)
                    <flux:select.option value="{{ $library->id }}">{{ $library->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter_room_id" label="Room">
                <flux:select.option value="">{{ __('All Rooms') }}</flux:select.option>
                @foreach ($this->rooms as $room)
                    <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter_seat_id" label="Seat">
                <flux:select.option value="">{{ __('All Seats') }}</flux:select.option>
                @foreach ($this->seats as $seat)
                    <flux:select.option value="{{ $seat->id }}">{{ $seat->seat_number }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">{{ __('Seat Attendance Register') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Each row shows the student, seat, and attendance pattern for the selected month.') }}
                </flux:text>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-left text-zinc-500">
                        <th class="pb-4 pr-4 font-medium">{{ __('Library') }}</th>
                        <th class="pb-4 pr-4 font-medium">{{ __('Room') }}</th>
                        <th class="pb-4 pr-4 font-medium">{{ __('Seat') }}</th>
                        <th class="pb-4 pr-4 font-medium">{{ __('Student') }}</th>
                        <th class="pb-4 pr-4 font-medium">{{ __('Present Days') }}</th>
                        <th class="pb-4 pr-4 font-medium">{{ __('Last Marked') }}</th>
                        <th class="pb-4 font-medium">{{ __('Monthly View') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($this->seatAttendance as $row)
                        @php
                            $markedDayLookup = array_flip($row['marked_day_numbers']);
                        @endphp
                        <tr>
                            <td class="py-4 pr-4 align-top">
                                <div class="font-medium">{{ $row['library'] }}</div>
                            </td>
                            <td class="py-4 pr-4 align-top">{{ $row['room'] }}</td>
                            <td class="py-4 pr-4 align-top">
                                <flux:badge color="zinc">{{ $row['seat'] }}</flux:badge>
                            </td>
                            <td class="py-4 pr-4 align-top">
                                <div class="font-medium">{{ $row['student'] }}</div>
                                <div class="text-xs text-zinc-500">{{ $row['student_email'] }}</div>
                            </td>
                            <td class="py-4 pr-4 align-top">
                                <div class="font-semibold">{{ $row['attendance_count'] }} / {{ $row['tracked_days'] }}</div>
                            </td>
                            <td class="py-4 pr-4 align-top">{{ $row['last_marked_at'] ?? __('Not marked yet') }}</td>
                            <td class="py-4 align-top">
                                <div class="flex min-w-[24rem] flex-wrap gap-1">
                                    @foreach ($this->monthDays as $dayNumber)
                                        <div
                                            class="flex h-7 w-7 items-center justify-center rounded-md text-[11px] font-medium
                                                {{ isset($markedDayLookup[$dayNumber]) ? 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-300' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                                            {{ $dayNumber }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                {{ __('No seat attendance records match the selected filters yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
