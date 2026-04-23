<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

use App\Models\User;
use App\Models\Room;
use App\Models\Shift;

use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithFileUploads, WithPagination;

    protected $paginationTheme = 'tailwind';

    // filters
    public $library_id;
    public $libraries = [];
    public $rooms = [];
    public $shifts = [];

    public $filter_room_id;
    public $filter_shift_id;

    // edit modal
    public $editId = null;
    public $name, $email, $profile_image;
    public $showEditModal = false;

    // detail modal
    public $showDetail = false;
    public $selectedStudent = null;

    public function mount()
    {
        $this->libraries = Auth::user()->libraries;
    }

    // ✅ PAGINATED STUDENTS
    #[Computed]
    public function students()
    {
        $query = User::where('role', 'student');

        if ($this->library_id) {
            $query->where('library_id', $this->library_id);
        }

        if ($this->filter_room_id || $this->filter_shift_id) {
            $query->whereHas('memberships', function ($q) {
                if ($this->filter_room_id) {
                    $q->whereHas('seat', function ($s) {
                        $s->where('room_id', $this->filter_room_id);
                    });
                }

                if ($this->filter_shift_id) {
                    $q->whereJsonContains('shift_ids', $this->filter_shift_id);
                }
            });
        }

        return $query->latest()->paginate(10);
    }

    // 🔄 Reset pagination on filter change
    public function updatingLibraryId()
    {
        $this->resetPage();
    }
    public function updatingFilterRoomId()
    {
        $this->resetPage();
    }
    public function updatingFilterShiftId()
    {
        $this->resetPage();
    }

    // 🔥 load filters
    public function updatedLibraryId()
    {
        $this->rooms = Room::where('library_id', $this->library_id)->get();
        $this->shifts = Shift::where('library_id', $this->library_id)->get();
    }

    // ✏️ EDIT
    public function edit($id)
    {
        $student = User::findOrFail($id);

        $this->editId = $id;
        $this->name = $student->name;
        $this->email = $student->email;

        $this->showEditModal = true;
    }

    // 💾 UPDATE
    public function update()
    {
        $student = User::findOrFail($this->editId);

        if ($this->profile_image) {
            $student->profile_image = $this->profile_image->store('students', 'public');
        }

        $student->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        $this->showEditModal = false;

        $this->reset(['name', 'email', 'profile_image', 'editId']);
    }

    public function delete($id)
    {
        User::find($id)?->delete();
    }

    // 🔍 DETAIL
    public function show($id)
    {
        $this->selectedStudent = User::with(['library', 'memberships.seat.room'])->find($id);

        $this->showDetail = true;
    }
};
?>

<section class="space-y-6">

    <!-- HEADER -->
    <flux:heading size="lg">🎓 Students</flux:heading>

    <!-- FILTERS -->
    <div class="grid md:grid-cols-4 gap-3">

        <flux:select wire:model.live="library_id" label="Library">
            <flux:select.option value="">All</flux:select.option>
            @foreach ($libraries as $lib)
                <flux:select.option value="{{ $lib->id }}">{{ $lib->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filter_room_id" label="Room">
            <flux:select.option value="">All</flux:select.option>
            @foreach ($rooms as $room)
                <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filter_shift_id" label="Shift">
            <flux:select.option value="">All</flux:select.option>
            @foreach ($shifts as $shift)
                <flux:select.option value="{{ $shift->id }}">{{ $shift->name }}</flux:select.option>
            @endforeach
        </flux:select>

    </div>

    <!-- TABLE -->
    <flux:table :paginate="$this->students">

        <flux:table.columns>
            <flux:table.column>Student</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>

            @foreach ($this->students as $student)
                <flux:table.row :key="$student->id">

                    <flux:table.cell class="flex gap-2 items-center">
                        <flux:avatar size="xs" src="{{ asset('storage/' . $student->profile_image) }}" />
                        {{ $student->name }}
                    </flux:table.cell>

                    <flux:table.cell>
                        {{ $student->email }}
                    </flux:table.cell>

                    <flux:table.cell align="end">

                        <flux:button size="sm" wire:click="show({{ $student->id }})">
                            View
                        </flux:button>

                        <flux:button size="sm" wire:click="edit({{ $student->id }})">
                            Edit
                        </flux:button>

                        <flux:button size="sm" variant="danger" wire:click="delete({{ $student->id }})">
                            Delete
                        </flux:button>

                    </flux:table.cell>

                </flux:table.row>
            @endforeach

        </flux:table.rows>

    </flux:table>

    <!-- EDIT MODAL -->
    <flux:modal wire:model="showEditModal">

        <flux:heading>Edit Student</flux:heading>

        <div class="space-y-3 mt-3">
            <flux:input wire:model="name" label="Name" />
            <flux:input wire:model="email" label="Email" />

            <input type="file" wire:model="profile_image" />

            <flux:button wire:click="update">
                Update
            </flux:button>
        </div>

    </flux:modal>

    <!-- DETAIL MODAL -->
    <flux:modal wire:model="showDetail">

        <flux:heading>{{ $selectedStudent?->name }}</flux:heading>

        <div class="mt-3 space-y-2">

            <img src="{{ asset('storage/' . $selectedStudent?->profile_image) }}" class="w-20 h-20 rounded">

            <p>Email: {{ $selectedStudent?->email }}</p>
            <p>Library: {{ $selectedStudent?->library?->name }}</p>

            <hr>

            <h3 class="font-semibold">Membership</h3>

            @foreach ($selectedStudent?->memberships ?? [] as $m)
                <div class="border p-2 rounded">
                    Seat: {{ $m->seat?->seat_number }} <br>
                    Room: {{ $m->seat?->room?->name }} <br>
                    Amount: ₹{{ $m->amount }}
                </div>
            @endforeach

        </div>

    </flux:modal>

</section>
