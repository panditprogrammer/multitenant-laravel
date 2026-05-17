<section class="space-y-8">
    <div class="relative w-full">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Attendance') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">
                    {{ __('Mark your daily attendance and review it in a monthly view') }}
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
            <flux:text class="text-sm text-zinc-500">{{ __('Library') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $currentMembership?->library?->name ?? __('Not assigned') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ $currentMembership?->library?->city ?? __('No city added') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Seat') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $currentMembership?->seat?->seat_number ?? __('Not assigned') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ $currentMembership?->seat?->room?->name ?? __('No room linked') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Present Days') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $monthPresentDays }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Attendance marks in the selected month') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Tracked Days') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $trackedDays }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Days available for marking so far this month') }}</flux:text>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="lg">{{ __('Daily Check-In') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('You can mark attendance once per day while your membership is active.') }}
                </flux:text>
            </div>

            <flux:button wire:click="markTodayAttendance" :disabled="!$currentMembership || $todayMarked">
                {{ $todayMarked ? __('Marked Today') : __('Mark Today Attendance') }}
            </flux:button>
        </div>

        @if (!$currentMembership)
            <flux:text class="mt-5 rounded-xl border border-dashed border-zinc-300 p-5 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-300">
                {{ __('No active membership with a seat is available yet. Once the owner assigns your seat, you will be able to mark attendance here.') }}
            </flux:text>
        @endif
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">{{ __('Monthly View') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Green means present, amber is today, and muted days are outside your active membership window.') }}
                </flux:text>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-7 gap-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500">
            <div>{{ __('Mon') }}</div>
            <div>{{ __('Tue') }}</div>
            <div>{{ __('Wed') }}</div>
            <div>{{ __('Thu') }}</div>
            <div>{{ __('Fri') }}</div>
            <div>{{ __('Sat') }}</div>
            <div>{{ __('Sun') }}</div>
        </div>

        <div class="mt-4 grid grid-cols-7 gap-3">
            @foreach ($calendarDays as $day)
                @if (!$day)
                    <div class="aspect-square rounded-xl border border-transparent"></div>
                @else
                    <div
                        class="aspect-square rounded-xl border p-3 text-sm transition
                            {{ $day['is_present'] ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300' : '' }}
                            {{ !$day['is_present'] && $day['is_today'] ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300' : '' }}
                            {{ !$day['is_present'] && !$day['is_today'] && $day['is_within_membership'] ? 'border-zinc-200 bg-zinc-50 text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-100' : '' }}
                            {{ !$day['is_within_membership'] ? 'border-zinc-100 bg-zinc-50/60 text-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-600' : '' }}">
                        <div class="flex h-full flex-col justify-between">
                            <span class="text-base font-semibold">{{ $day['day_number'] }}</span>
                            <span class="text-[11px]">
                                @if ($day['is_present'])
                                    {{ __('Present') }}
                                @elseif ($day['is_today'] && $day['is_within_membership'])
                                    {{ __('Pending') }}
                                @elseif (!$day['is_within_membership'])
                                    {{ __('N/A') }}
                                @elseif ($day['is_future'])
                                    {{ __('Upcoming') }}
                                @else
                                    {{ __('Missed') }}
                                @endif
                            </span>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
