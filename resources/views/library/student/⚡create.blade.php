<?php

use Livewire\Component;
use App\Models\User;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Shift;
use App\Models\Membership;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $name, $email, $password;

    public $library_id, $room_id, $seat_id;
    public $shift_ids = [];

    public $libraries = [];
    public $rooms = [];
    public $shifts = [];

    public $availableSeats = [];
    public $amount = 0;

    public function mount()
    {
        $this->libraries = Auth::user()->libraries;
        $this->students = User::where(['role' => 'student','library_id' => $this->library_id])->get();
    }

    // ✅ Load rooms + shifts
    public function updatedLibraryId($value)
    {
        $this->rooms = Room::where('library_id', $value)->get();
        $this->shifts = Shift::where('library_id', $value)->get();

        $this->reset(['room_id', 'seat_id', 'shift_ids', 'availableSeats', 'amount']);
    }

    // ✅ Room selected → calculate price
    public function updatedRoomId()
    {
        $this->calculatePrice();
        $this->loadAvailableSeats();
    }

    // ✅ Shift filter
    public function updatedShiftIds()
    {
        $this->loadAvailableSeats();
    }

    // ✅ PRICE LOGIC (FINAL)
    public function calculatePrice()
    {
        $room = Room::with('library')->find($this->room_id);

        if (!$room || !$room->library) {
            $this->amount = 0;
            return;
        }

        $library = $room->library;

        if ($room->type === 'AC') {
            $this->amount = $library->ac_price ?? 0;
        } else {
            $this->amount = $library->normal_price ?? 0;
        }
    }

    // ✅ SEAT AVAILABILITY (SHIFT BASED)
    public function loadAvailableSeats()
    {
        if (!$this->room_id || empty($this->shift_ids)) {
            $this->availableSeats = [];
            return;
        }

        $libraryId = $this->library_id;

        $this->availableSeats = Seat::where('room_id', $this->room_id)
            ->get()
            ->map(function ($seat) use ($libraryId) {
                $membership = Membership::where('library_id', $libraryId)
                    ->where('seat_id', $seat->id)
                    ->where('status', 'active')
                    ->whereDate('end_date', '>=', now())
                    ->where(function ($query) {
                        foreach ($this->shift_ids as $shiftId) {
                            $query->orWhereJsonContains('shift_ids', $shiftId);
                        }
                    })
                    ->first();

                $occupied = $membership ? true : false;

                // 🟡 Expiring within 3 days
                $expiring = $membership && now()->diffInDays($membership->end_date, false) <= 3;

                return [
                    'id' => $seat->id,
                    'number' => $seat->seat_number,
                    'occupied' => $occupied,
                    'expiring' => $expiring,
                ];
            });
    }

    // 🎯 Auto best seat
    public function recommendSeat()
    {
        $seat = collect($this->availableSeats)->firstWhere('occupied', false);

        if ($seat) {
            $this->seat_id = $seat['id'];
        }
    }

    // ✅ SAVE
    public function save()
    {
        $this->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'library_id' => 'required',
            'room_id' => 'required',
            'seat_id' => 'required',
            'shift_ids' => 'required|array',
        ]);

        DB::transaction(function () {
            $student = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password ?? '12345678'),
                'role' => 'student',
                'library_id' => $this->library_id,
            ]);

            Membership::create([
                'library_id' => $this->library_id,
                'user_id' => $student->id,
                'seat_id' => $this->seat_id,
                'shift_ids' => $this->shift_ids,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'amount' => $this->amount,
                'status' => 'active',
            ]);
        });

        session()->flash('success', 'Student admitted successfully');

        $this->reset(['name', 'email', 'password', 'library_id', 'room_id', 'seat_id', 'shift_ids', 'amount']);
    }
};
?>

<section class="space-y-6">

    <flux:heading size="lg">🎓 Student Admission + Seat Allotment</flux:heading>

    @if (session('success'))
        <div class="bg-green-100 p-2 rounded">{{ session('success') }}</div>
    @endif

    <form wire:submit="save" class="grid md:grid-cols-4 gap-3">

        <!-- Library -->
        <flux:select wire:model.live="library_id" label="Library">
            <flux:select.option value="">Select</flux:select.option>
            @foreach ($libraries as $lib)
                <flux:select.option value="{{ $lib->id }}">{{ $lib->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <!-- Room -->
        <flux:select wire:model.live="room_id" label="Room">
            <flux:select.option value="">Select</flux:select.option>
            @foreach ($rooms as $room)
                <flux:select.option value="{{ $room->id }}">
                    {{ $room->name }} ({{ $room->type }})
                </flux:select.option>
            @endforeach
        </flux:select>

        <!-- Shifts -->
        <div>
            <label class="font-semibold">Shifts</label>
            @foreach ($shifts as $shift)
                <div>
                    <input type="checkbox" wire:model.live="shift_ids" value="{{ $shift->id }}">
                    {{ $shift->name }}
                </div>
            @endforeach
        </div>

        <!-- Price -->
        <flux:input wire:model="amount" label="Price" readonly />

        <!-- Student -->
        <flux:input wire:model="name" label="Student Name" />
        <flux:input wire:model="email" label="Email" />

        <!-- 🎬 Seat Layout -->
        <div class="md:col-span-4">

            <div class="flex justify-between">
                <label class="font-semibold">Select Seat</label>

                <flux:button type="button" size="sm" wire:click="recommendSeat">
                    🎯 Auto Seat
                </flux:button>
            </div>

            <div class="grid grid-cols-8 gap-2 mt-3">

                @foreach ($availableSeats as $seat)
                    <div @if (!$seat['occupied']) wire:click="$set('seat_id', {{ $seat['id'] }})" @endif
                        class="p-3 text-center rounded-xl text-sm cursor-pointer

                        {{ $seat['occupied'] && !$seat['expiring'] ? 'bg-red-500 text-white' : '' }}
                        {{ $seat['expiring'] ? 'bg-yellow-400 text-black' : '' }}
                        {{ !$seat['occupied'] ? 'bg-green-500 text-white' : '' }}
                        {{ $seat_id == $seat['id'] ? 'ring-4 ring-blue-500' : '' }}
                        ">
                        {{ $seat['number'] }}
                    </div>
                @endforeach

            </div>

            <!-- Legend -->
            <div class="flex gap-3 mt-3 text-sm">
                <span class="bg-green-500 text-white px-2 py-1 rounded">Available</span>
                <span class="bg-red-500 text-white px-2 py-1 rounded">Occupied</span>
                <span class="bg-yellow-400 px-2 py-1 rounded">Expiring</span>
            </div>

        </div>

        <flux:button type="submit" variant="primary" class="mt-6">
            Create Admission
        </flux:button>

    </form>

</section>
