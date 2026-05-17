<?php

namespace App\Livewire\Student;

use App\Models\Attendance;
use App\Models\Membership;
use Carbon\Carbon;
use Livewire\Component;

class AttendancePage extends Component
{
    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    public function markTodayAttendance(): void
    {
        $membership = $this->currentMembership();

        if (!$membership || !$membership->seat?->room) {
            $this->dispatch('error', ['message' => 'No active seat membership found for attendance.']);

            return;
        }

        $today = today();

        if ($membership->start_date?->isAfter($today) || $membership->end_date?->isBefore($today)) {
            $this->dispatch('error', ['message' => 'Attendance can only be marked during your active membership period.']);

            return;
        }

        $attendance = Attendance::firstOrCreate(
            [
                'user_id' => auth()->id(),
                'attended_on' => $today->toDateString(),
            ],
            [
                'membership_id' => $membership->id,
                'library_id' => $membership->library_id,
                'room_id' => $membership->seat->room_id,
                'seat_id' => $membership->seat_id,
            ]
        );

        $this->month = $today->format('Y-m');

        $this->dispatch('success', ['message' => $attendance->wasRecentlyCreated ? 'Attendance marked for today.' : 'Today attendance is already marked.']);
    }

    public function render()
    {
        $membership = $this->currentMembership();
        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $attendanceMap = Attendance::query()
            ->where('user_id', auth()->id())
            ->whereBetween('attended_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $attendance) => $attendance->attended_on->toDateString());

        $calendarDays = collect();

        for ($offset = 1; $offset < $monthStart->dayOfWeekIso; $offset++) {
            $calendarDays->push(null);
        }

        foreach (range(1, $monthEnd->day) as $dayNumber) {
            $date = $monthStart->copy()->day($dayNumber);
            $dateKey = $date->toDateString();
            $isWithinMembership = $membership
                && $membership->start_date
                && $membership->end_date
                && $date->betweenIncluded($membership->start_date, $membership->end_date);

            $calendarDays->push([
                'date' => $dateKey,
                'day_number' => $dayNumber,
                'is_future' => $date->isFuture(),
                'is_today' => $date->isToday(),
                'is_present' => $attendanceMap->has($dateKey),
                'is_within_membership' => $isWithinMembership,
            ]);
        }

        while ($calendarDays->count() % 7 !== 0) {
            $calendarDays->push(null);
        }

        $presentDays = $attendanceMap->count();
        $elapsedDays = 0;

        if ($membership && $membership->start_date && $membership->end_date) {
            $trackedStart = $membership->start_date->greaterThan($monthStart)
                ? $membership->start_date->copy()
                : $monthStart->copy();

            $trackedEnd = $monthEnd->copy();

            if ($membership->end_date->lessThan($trackedEnd)) {
                $trackedEnd = $membership->end_date->copy();
            }

            if (today()->lessThan($trackedEnd)) {
                $trackedEnd = today()->copy();
            }

            if ($trackedStart->lessThanOrEqualTo($trackedEnd)) {
                $elapsedDays = $trackedStart->diffInDays($trackedEnd) + 1;
            }
        }

        return view('livewire.student.attendance-page', [
            'calendarDays' => $calendarDays,
            'currentMembership' => $membership,
            'monthLabel' => $monthStart->format('F Y'),
            'monthPresentDays' => $presentDays,
            'todayMarked' => $attendanceMap->has(today()->toDateString()),
            'trackedDays' => $elapsedDays,
        ]);
    }

    protected function currentMembership(): ?Membership
    {
        return Membership::query()
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->with(['library', 'seat.room'])
            ->orderByDesc('end_date')
            ->first();
    }

    protected function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
    }
}
