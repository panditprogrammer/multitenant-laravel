<?php

use Livewire\Component;
use App\Models\User;
use App\Models\Seat;
use App\Models\Membership;
use App\Models\Library;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public $library_id, $user_id, $seat_id;
    public $start_date;
    public $amount = 0;

    public $shift_ids = [];

    public $library = null;

    public $libraries = [];
    public $students = [];
    public $seats = [];
    public $memberships = [];

    public function mount()
    {
        $this->libraries = Auth::user()->libraries;
        $this->loadMemberships();
    }

    public function loadMemberships()
    {
        $this->memberships = Membership::with(['student', 'seat.room', 'library'])
            ->whereIn('library_id', auth()->user()->libraries->pluck('id'))
            ->latest()
            ->get();
    }

    public function updatedLibraryId()
    {
        $this->students = User::where('role', 'student')->where('library_id', $this->library_id)->get();

        $this->seats = Seat::with('room')->whereHas('room', fn($q) => $q->where('library_id', $this->library_id))->get();

        $this->library = Library::findOrFail($this->library_id);
        $this->resetFormState();
    }

    public function updated($field)
    {
        if (in_array($field, ['seat_id', 'shift_ids'])) {
            $this->resetErrorBag();
            $this->calculateAmount();
        }
    }

    // 🔥 PRICING
    public function calculateAmount()
    {
        $library = Library::find($this->library_id);
        $seat = Seat::with('room')->find($this->seat_id);

        if (!$library || !$seat) {
            $this->amount = 0;
            return;
        }

        $room = $seat->room;

        $rate = $room->type === 'AC' ? $library->ac_price : $library->normal_price;

        if ($rate === null) {
            $this->addError('seat_id', 'Pricing not configured');
            return;
        }

        $this->amount = $rate * max(count($this->shift_ids), 1);
    }

    // 🔥 DATE RANGE (MONTHLY)
    private function getEndDate()
    {
        return Carbon::parse($this->start_date)->addMonth()->subDay();
    }

    // 🔥 CONFLICT CHECK
    private function hasSeatConflict($endDate)
    {
        return Membership::where('seat_id', $this->seat_id)
            ->where('library_id', $this->library_id)
            ->where(function ($q) use ($endDate) {
                $q->where('start_date', '<=', $endDate)->where('end_date', '>=', $this->start_date);
            })
            ->whereHas('shifts', function ($sq) {
                $sq->whereIn('shift_id', $this->shift_ids);
            })
            ->exists();
    }

    private function hasStudentConflict($endDate)
    {
        return Membership::where('user_id', $this->user_id)
            ->where('library_id', $this->library_id)
            ->where(function ($q) use ($endDate) {
                $q->where('start_date', '<=', $endDate)->where('end_date', '>=', $this->start_date);
            })
            ->whereHas('shifts', function ($sq) {
                $sq->whereIn('shift_id', $this->shift_ids);
            })
            ->exists();
    }

    public function save()
    {
        $this->validate([
            'library_id' => 'required|exists:libraries,id',
            'user_id' => 'required|exists:users,id',
            'seat_id' => 'required|exists:seats,id',
            'start_date' => 'required|date',
        ]);

        if (empty($this->shift_ids)) {
            $this->addError('shift_ids', 'Select shifts');
            return;
        }

        if (count($this->shift_ids) > 3) {
            $this->addError('shift_ids', 'Max 3 shifts allowed');
            return;
        }

        // 🔐 security
        if (!auth()->user()->libraries->pluck('id')->contains($this->library_id)) {
            abort(403);
        }

        $this->calculateAmount();

        $endDate = $this->getEndDate();

        if ($this->hasSeatConflict($endDate)) {
            $this->addError('seat_id', 'Seat already booked');
            return;
        }

        if ($this->hasStudentConflict($endDate)) {
            $this->addError('user_id', 'Student already booked');
            return;
        }

        $membership = Membership::create([
            'user_id' => $this->user_id,
            'seat_id' => $this->seat_id,
            'library_id' => $this->library_id,
            'start_date' => $this->start_date,
            'end_date' => $endDate,
            'amount' => $this->amount,
        ]);

        $membership->shifts()->sync($this->shift_ids);

        $this->resetFormState();
        $this->loadMemberships();

        $this->dispatch('success', ['message' => 'Membership created']);
    }

    private function resetFormState()
    {
        $this->reset(['user_id', 'seat_id', 'start_date', 'shift_ids', 'amount']);
    }
};
?>


<section class="space-y-6">

    <flux:heading size="lg">Membership</flux:heading>

    <form wire:submit="save" class="grid md:grid-cols-4 gap-3">

        <flux:select wire:model.live="library_id" label="Library">
            <option value="">Select</option>
            @foreach ($libraries as $lib)
                <option value="{{ $lib->id }}">{{ $lib->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model="user_id" label="Student">
            <option value="">Select</option>
            @foreach ($students as $s)
                <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="seat_id" label="Seat">
            <option value="">Select</option>
            @foreach ($seats as $seat)
                <option value="{{ $seat->id }}">
                    {{ $seat->seat_number }} ({{ $seat->room->type }})
                </option>
            @endforeach
        </flux:select>

        <flux:input type="date" wire:model="start_date" label="Start Date" />

        @if ($start_date)
            <flux:badge color="blue">
                Valid Till: {{ \Carbon\Carbon::parse($start_date)->addMonth()->subDay()->format('d M Y') }}
            </flux:badge>
        @endif

        @if ($library)
            <flux:select multiple wire:model="shift_ids" label="Select Shifts">
                @foreach ($library->shifts as $shift)
                    <option value="{{ $shift->id }}">
                        {{ $shift->name }} ({{ $shift->start_time }} - {{ $shift->end_time }})
                    </option>
                @endforeach
            </flux:select>
        @endif

        @if ($amount)
            <flux:badge color="green">₹{{ $amount }}</flux:badge>
        @endif

        <flux:button type="submit">Save</flux:button>

    </form>

    <hr>

    <flux:heading size="lg">Memberships</flux:heading>

    <flux:table>

        <flux:table.columns>
            <flux:table.column>Student</flux:table.column>
            <flux:table.column>Seat</flux:table.column>
            <flux:table.column>Plan</flux:table.column>
            <flux:table.column>Dates</flux:table.column>
            <flux:table.column>Amount</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>

            @foreach ($memberships as $m)
                <flux:table.row>

                    <flux:table.cell>{{ $m->student->name }}</flux:table.cell>

                    <flux:table.cell>
                        {{ $m->seat->seat_number }} ({{ $m->seat->room->type }})
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($m->shifts)
                            @foreach ($m->shifts as $s)
                                <div>{{ $s->name }}</div>
                            @endforeach
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        {{ $m->start_date->format('d M') }} → {{ $m->end_date->format('d M') }}
                    </flux:table.cell>

                    <flux:table.cell>₹{{ $m->amount }}</flux:table.cell>

                </flux:table.row>
            @endforeach

        </flux:table.rows>

    </flux:table>

</section>
