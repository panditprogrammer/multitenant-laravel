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
                <div class="min-w-36 text-center text-sm font-medium">{{ $monthLabel }}</div>
                <flux:button variant="outline" wire:click="nextMonth">{{ __('Next') }}</flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Libraries in View') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $attendanceStats['libraries'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Libraries covered by the current filters') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Rooms in View') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $attendanceStats['rooms'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Rooms that currently match the filter') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Seats Tracked') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $attendanceStats['seats'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Seat assignments visible in the selected month') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Marked Today') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $attendanceStats['marked_today'] }}</flux:heading>
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
                @foreach ($libraries as $library)
                    <flux:select.option value="{{ $library->id }}">{{ $library->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter_room_id" label="Room">
                <flux:select.option value="">{{ __('All Rooms') }}</flux:select.option>
                @foreach ($rooms as $room)
                    <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter_seat_id" label="Seat">
                <flux:select.option value="">{{ __('All Seats') }}</flux:select.option>
                @foreach ($seats as $seat)
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
                    @forelse ($seatAttendance as $row)
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
                                    @foreach ($monthDays as $dayNumber)
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
