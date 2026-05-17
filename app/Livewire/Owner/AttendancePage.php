<?php

namespace App\Livewire\Owner;

use App\Models\Attendance;
use App\Models\Library;
use App\Models\Membership;
use App\Models\Room;
use App\Models\Seat;
use Carbon\Carbon;
use Livewire\Component;

class AttendancePage extends Component
{
    public string $filter_library_id = '';

    public string $filter_room_id = '';

    public string $filter_seat_id = '';

    public string $month = '';

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

    public function render()
    {
        $libraries = Library::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        $rooms = Room::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
            ->orderBy('name')
            ->get();

        $seats = Seat::query()
            ->whereHas('room.library', fn ($query) => $query->where('user_id', auth()->id()))
            ->when($this->filter_library_id, fn ($query) => $query->whereHas('room', fn ($roomQuery) => $roomQuery->where('library_id', $this->filter_library_id)))
            ->when($this->filter_room_id, fn ($query) => $query->where('room_id', $this->filter_room_id))
            ->orderBy('seat_number')
            ->get();

        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthDays = range(1, $monthEnd->day);

        $memberships = Membership::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
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

        $seatAttendance = $memberships->map(function (Membership $membership) use ($monthStart, $monthEnd) {
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

        $attendanceStats = [
            'libraries' => $seatAttendance->pluck('library_id')->filter()->unique()->count(),
            'rooms' => $seatAttendance->pluck('room_id')->filter()->unique()->count(),
            'seats' => $seatAttendance->count(),
            'marked_today' => Attendance::query()
                ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
                ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
                ->when($this->filter_room_id, fn ($query) => $query->where('room_id', $this->filter_room_id))
                ->when($this->filter_seat_id, fn ($query) => $query->where('seat_id', $this->filter_seat_id))
                ->whereDate('attended_on', today())
                ->count(),
        ];

        return view('livewire.owner.attendance-page', [
            'attendanceStats' => $attendanceStats,
            'libraries' => $libraries,
            'monthDays' => $monthDays,
            'monthLabel' => $monthStart->format('F Y'),
            'rooms' => $rooms,
            'seatAttendance' => $seatAttendance,
            'seats' => $seats,
        ]);
    }

    protected function monthStart(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
    }
}
