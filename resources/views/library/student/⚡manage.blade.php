<?php

use App\Models\Library;
use App\Models\Membership;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $filter_library_id = '';
    public $filter_room_id = '';
    public $filter_shift_id = '';

    public $student_name = '';
    public $student_email = '';
    public $student_password = '';
    public $student_profile_image = null;

    public $form_library_id = '';
    public $form_room_id = '';
    public $form_seat_id = '';
    public $form_shift_ids = [];
    public $amount = 0;

    public $editingId = null;
    public $editingMembershipId = null;
    public $selectedStudentId = null;

    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

    public function startCreate()
    {
        $this->resetCreateForm();
    }

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function libraries()
    {
        return Library::where('user_id', auth()->id())
            ->withCount(['students', 'rooms', 'shifts'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function filterRooms()
    {
        return Room::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function filterShifts()
    {
        return Shift::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function admissionRooms()
    {
        if (!$this->form_library_id) {
            return collect();
        }

        return Room::where('library_id', $this->form_library_id)
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function admissionShifts()
    {
        if (!$this->form_library_id) {
            return collect();
        }

        return Shift::where('library_id', $this->form_library_id)
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableSeats()
    {
        if (!$this->form_room_id || empty($this->form_shift_ids)) {
            return collect();
        }

        return Seat::where('room_id', $this->form_room_id)
            ->orderBy('seat_number')
            ->get()
            ->map(function ($seat) {
                $membership = Membership::where('library_id', $this->form_library_id)
                    ->where('seat_id', $seat->id)
                    ->where('status', 'active')
                    ->whereDate('end_date', '>=', now())
                    ->where(function ($query) {
                        $query->whereHas('shifts', function ($shiftQuery) {
                            $shiftQuery->whereIn('shifts.id', $this->form_shift_ids);
                        })->orWhere(function ($legacyQuery) {
                            foreach ($this->form_shift_ids as $shiftId) {
                                $legacyQuery->orWhereJsonContains('shift_ids', $shiftId);
                            }
                        });
                    })
                    ->first();

                $occupied = (bool) $membership;
                $expiring = $membership && now()->diffInDays($membership->end_date, false) <= 3;

                return [
                    'id' => $seat->id,
                    'number' => $seat->seat_number,
                    'occupied' => $occupied,
                    'expiring' => $expiring,
                    'is_active' => (bool) $seat->is_active,
                ];
            });
    }

    #[Computed]
    public function students()
    {
        return User::query()
            ->where('role', 'student')
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->with([
                'library',
                'memberships' => fn ($query) => $query
                    ->whereHas('library', fn ($libraryQuery) => $libraryQuery->where('user_id', auth()->id()))
                    ->with(['seat.room', 'shifts'])
                    ->latest('end_date'),
            ])
            ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
            ->when($this->filter_room_id || $this->filter_shift_id, function ($query) {
                $query->whereHas('memberships', function ($membershipQuery) {
                    if ($this->filter_room_id) {
                        $membershipQuery->whereHas('seat', fn ($seatQuery) => $seatQuery->where('room_id', $this->filter_room_id));
                    }

                    if ($this->filter_shift_id) {
                        $membershipQuery->where(function ($shiftQuery) {
                            $shiftQuery->whereHas('shifts', fn ($query) => $query->where('shifts.id', $this->filter_shift_id))
                                ->orWhereJsonContains('shift_ids', $this->filter_shift_id);
                        });
                    }
                });
            })
            ->tap(function ($query) {
                if ($this->sortBy === 'library') {
                    $query->join('libraries', 'libraries.id', '=', 'users.library_id')
                        ->orderBy('libraries.name', $this->sortDirection)
                        ->select('users.*');

                    return;
                }

                $query->orderBy($this->sortBy, $this->sortDirection);
            })
            ->paginate(10);
    }

    #[Computed]
    public function studentStats()
    {
        $students = User::query()
            ->where('role', 'student')
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->with([
                'library',
                'memberships' => fn ($query) => $query
                    ->whereHas('library', fn ($libraryQuery) => $libraryQuery->where('user_id', auth()->id())),
            ])
            ->get();

        $activeStudents = $students->filter(function ($student) {
            return $student->memberships->contains(fn ($membership) => $membership->status === 'active' && $membership->end_date && !$membership->end_date->isPast());
        });

        $expiringStudents = $students->filter(function ($student) {
            return $student->memberships->contains(fn ($membership) => $membership->status === 'active'
                && $membership->end_date
                && !$membership->end_date->isPast()
                && now()->diffInDays($membership->end_date, false) <= 3);
        });

        return [
            'students' => $students->count(),
            'active_students' => $activeStudents->count(),
            'expiring_students' => $expiringStudents->count(),
            'libraries' => $students->pluck('library_id')->filter()->unique()->count(),
        ];
    }

    #[Computed]
    public function selectedStudent()
    {
        if (!$this->selectedStudentId) {
            return null;
        }

        return User::query()
            ->where('role', 'student')
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->with([
                'library',
                'memberships' => fn ($query) => $query
                    ->whereHas('library', fn ($libraryQuery) => $libraryQuery->where('user_id', auth()->id()))
                    ->with(['seat.room', 'shifts'])
                    ->latest('end_date'),
            ])
            ->find($this->selectedStudentId);
    }

    public function updatedFilterLibraryId()
    {
        $this->filter_room_id = '';
        $this->filter_shift_id = '';
        $this->resetPage();
    }

    public function updatedFilterRoomId()
    {
        $this->resetPage();
    }

    public function updatedFilterShiftId()
    {
        $this->resetPage();
    }

    public function updatedFormLibraryId()
    {
        $this->form_room_id = '';
        $this->form_seat_id = '';
        $this->form_shift_ids = [];
        $this->amount = 0;
    }

    public function updatedFormRoomId()
    {
        $this->form_seat_id = '';
        $this->calculatePrice();
    }

    public function updatedFormShiftIds()
    {
        $this->form_seat_id = '';
    }

    public function calculatePrice()
    {
        $room = Room::with('library')
            ->where('library_id', $this->form_library_id)
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->find($this->form_room_id);

        if (!$room || !$room->library) {
            $this->amount = 0;
            return;
        }

        $this->amount = $room->type === 'AC'
            ? (float) ($room->library->ac_price ?? 0)
            : (float) ($room->library->normal_price ?? 0);
    }

    public function recommendSeat()
    {
        $seat = $this->availableSeats->first(fn ($item) => !$item['occupied'] && $item['is_active']);

        if ($seat) {
            $this->form_seat_id = $seat['id'];
        }
    }

    public function saveStudent()
    {
        $this->validate([
            'student_name' => 'required|min:2',
            'student_email' => 'required|email|unique:users,email',
            'student_password' => 'nullable|min:8',
            'student_profile_image' => 'nullable|file|image|max:2048',
            'form_library_id' => ['required', Rule::exists('libraries', 'id')->where(fn ($query) => $query->where('user_id', auth()->id()))],
            'form_room_id' => ['required', Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('library_id', $this->form_library_id))],
            'form_seat_id' => ['required', Rule::exists('seats', 'id')->where(fn ($query) => $query->where('room_id', $this->form_room_id))],
            'form_shift_ids' => 'required|array|min:1',
            'form_shift_ids.*' => ['required', Rule::exists('shifts', 'id')->where(fn ($query) => $query->where('library_id', $this->form_library_id))],
            'amount' => 'required|numeric|min:0',
        ]);

        $conflictingMembership = Membership::where('library_id', $this->form_library_id)
            ->where('seat_id', $this->form_seat_id)
            ->where('status', 'active')
            ->whereDate('end_date', '>=', now())
            ->where(function ($query) {
                $query->whereHas('shifts', fn ($shiftQuery) => $shiftQuery->whereIn('shifts.id', $this->form_shift_ids))
                    ->orWhere(function ($legacyQuery) {
                        foreach ($this->form_shift_ids as $shiftId) {
                            $legacyQuery->orWhereJsonContains('shift_ids', $shiftId);
                        }
                    });
            })
            ->exists();

        if ($conflictingMembership) {
            $this->addError('form_seat_id', 'Selected seat is already occupied for the chosen shift.');
            return;
        }

        DB::transaction(function () {
            $imagePath = $this->student_profile_image
                ? $this->student_profile_image->store('students', 'public')
                : null;

            $student = User::create([
                'name' => $this->student_name,
                'email' => $this->student_email,
                'password' => Hash::make($this->student_password ?: '12345678'),
                'profile_image' => $imagePath,
                'role' => 'student',
                'library_id' => $this->form_library_id,
            ]);

            $membership = Membership::create([
                'library_id' => $this->form_library_id,
                'user_id' => $student->id,
                'seat_id' => $this->form_seat_id,
                'shift_ids' => $this->form_shift_ids,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
                'amount' => $this->amount,
                'status' => 'active',
            ]);

            $membership->shifts()->sync($this->form_shift_ids);
        });

        $this->dispatch('success', ['message' => 'Student admitted successfully!']);
        $this->resetCreateForm();
        $this->resetPage();
    }

    public function edit($id)
    {
        $student = User::query()
            ->where('role', 'student')
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->with(['memberships' => fn ($query) => $query
                ->whereHas('library', fn ($libraryQuery) => $libraryQuery->where('user_id', auth()->id()))
                ->with(['seat.room', 'shifts'])
                ->latest('end_date')])
            ->findOrFail($id);

        $membership = $student->memberships->first(fn ($item) => $item->status === 'active' && $item->end_date && !$item->end_date->isPast())
            ?? $student->memberships->first();

        $this->editingId = $student->id;
        $this->editingMembershipId = $membership?->id;
        $this->student_name = $student->name;
        $this->student_email = $student->email;
        $this->student_profile_image = null;
        $this->student_password = '';
        $this->form_library_id = (string) ($student->library_id ?? '');
        $this->form_room_id = (string) ($membership?->seat?->room_id ?? '');
        $this->form_seat_id = (string) ($membership?->seat_id ?? '');
        $this->form_shift_ids = $membership?->shifts->pluck('id')->map(fn ($id) => (string) $id)->all()
            ?: array_map('strval', $membership?->shift_ids ?? []);
        $this->amount = (float) ($membership?->amount ?? 0);
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function updateStudent()
    {
        $this->validate([
            'student_name' => 'required|min:2',
            'student_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'student_password' => 'nullable|min:8',
            'student_profile_image' => 'nullable|file|image|max:2048',
            'form_library_id' => ['required', Rule::exists('libraries', 'id')->where(fn ($query) => $query->where('user_id', auth()->id()))],
            'form_room_id' => ['required', Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('library_id', $this->form_library_id))],
            'form_seat_id' => ['required', Rule::exists('seats', 'id')->where(fn ($query) => $query->where('room_id', $this->form_room_id))],
            'form_shift_ids' => 'required|array|min:1',
            'form_shift_ids.*' => ['required', Rule::exists('shifts', 'id')->where(fn ($query) => $query->where('library_id', $this->form_library_id))],
            'amount' => 'required|numeric|min:0',
        ]);

        $student = User::query()
            ->where('role', 'student')
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->with(['memberships' => fn ($query) => $query
                ->whereHas('library', fn ($libraryQuery) => $libraryQuery->where('user_id', auth()->id()))])
            ->findOrFail($this->editingId);

        $conflictingMembership = Membership::where('library_id', $this->form_library_id)
            ->where('seat_id', $this->form_seat_id)
            ->where('status', 'active')
            ->whereDate('end_date', '>=', now())
            ->when($this->editingMembershipId, fn ($query) => $query->where('id', '!=', $this->editingMembershipId))
            ->where(function ($query) {
                $query->whereHas('shifts', fn ($shiftQuery) => $shiftQuery->whereIn('shifts.id', $this->form_shift_ids))
                    ->orWhere(function ($legacyQuery) {
                        foreach ($this->form_shift_ids as $shiftId) {
                            $legacyQuery->orWhereJsonContains('shift_ids', $shiftId);
                        }
                    });
            })
            ->exists();

        if ($conflictingMembership) {
            $this->addError('form_seat_id', 'Selected seat is already occupied for the chosen shift.');
            return;
        }

        $imagePath = $student->profile_image;

        if ($this->student_profile_image) {
            if ($student->profile_image) {
                Storage::disk('public')->delete($student->profile_image);
            }

            $imagePath = $this->student_profile_image->store('students', 'public');
        }

        DB::transaction(function () use ($student, $imagePath) {
            $payload = [
                'name' => $this->student_name,
                'email' => $this->student_email,
                'profile_image' => $imagePath,
                'library_id' => $this->form_library_id,
            ];

            if ($this->student_password) {
                $payload['password'] = Hash::make($this->student_password);
            }

            $student->update($payload);

            $membership = $this->editingMembershipId
                ? $student->memberships->firstWhere('id', $this->editingMembershipId)
                : null;

            if (!$membership) {
                $membership = Membership::create([
                    'library_id' => $this->form_library_id,
                    'user_id' => $student->id,
                    'seat_id' => $this->form_seat_id,
                    'shift_ids' => $this->form_shift_ids,
                    'start_date' => now(),
                    'end_date' => now()->addMonth(),
                    'amount' => $this->amount,
                    'status' => 'active',
                ]);
            } else {
                $membership->update([
                    'library_id' => $this->form_library_id,
                    'seat_id' => $this->form_seat_id,
                    'shift_ids' => $this->form_shift_ids,
                    'amount' => $this->amount,
                ]);
            }

            $membership->shifts()->sync($this->form_shift_ids);
        });

        $this->dispatch('success', ['message' => 'Student updated successfully!']);
        $this->resetEditForm();
    }

    public function delete($id)
    {
        User::query()
            ->where('role', 'student')
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->findOrFail($id)
            ->delete();

        $this->dispatch('success', ['message' => 'Student deleted successfully!']);
        $this->resetPage();
    }

    public function show($id)
    {
        User::query()
            ->where('role', 'student')
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->findOrFail($id);

        $this->selectedStudentId = $id;
    }

    public function resetCreateForm()
    {
        $this->reset([
            'student_name',
            'student_email',
            'student_password',
            'student_profile_image',
            'form_library_id',
            'form_room_id',
            'form_seat_id',
            'form_shift_ids',
            'amount',
        ]);

        $this->amount = 0;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function resetEditForm()
    {
        $this->reset([
            'editingId',
            'editingMembershipId',
            'student_name',
            'student_email',
            'student_password',
            'student_profile_image',
            'form_library_id',
            'form_room_id',
            'form_seat_id',
            'form_shift_ids',
            'amount',
        ]);

        $this->amount = 0;
        $this->resetErrorBag();
        $this->resetValidation();
    }
};
?>

<section class="space-y-8">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Manage Students') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Create admissions and manage students across your libraries') }}</flux:subheading>
            </div>

            <flux:modal.trigger name="create-student-modal">
                <flux:button wire:click="startCreate" x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-student-modal')">
                    {{ __('Create Student') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Students') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->studentStats['students'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Total students under your libraries') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Active Memberships') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->studentStats['active_students'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Students with an active membership') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Expiring Soon') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->studentStats['expiring_students'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Memberships ending within 3 days') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Libraries') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->studentStats['libraries'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Libraries currently serving students') }}</flux:text>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div>
            <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Filter students by library, room, and shift assignment.') }}</flux:text>
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
                @foreach ($this->filterRooms as $room)
                    <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter_shift_id" label="Shift">
                <flux:select.option value="">{{ __('All Shifts') }}</flux:select.option>
                @foreach ($this->filterShifts as $shift)
                    <flux:select.option value="{{ $shift->id }}">{{ $shift->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="space-y-4">
        <flux:table :paginate="$this->students">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">
                    {{ __('Student') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'library'" :direction="$sortDirection" wire:click="sort('library')">
                    {{ __('Library') }}
                </flux:table.column>
                <flux:table.column>{{ __('Seat & Room') }}</flux:table.column>
                <flux:table.column>{{ __('Membership') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                    {{ __('Created') }}
                </flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->students as $student)
                    @php
                        $currentMembership = $student->memberships->first(fn ($membership) => $membership->status === 'active' && $membership->end_date && !$membership->end_date->isPast());
                        $expiringSoon = $currentMembership && now()->diffInDays($currentMembership->end_date, false) <= 3;
                    @endphp
                    <flux:table.row :key="$student->id">
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:avatar size="xs" src="{{ $student->profile_image_url }}" />

                            <div>
                                <div class="font-semibold">{{ $student->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $student->email }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="font-medium">{{ $student->library?->name ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $student->library?->city ?? __('City not set') }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($currentMembership?->seat)
                                <div class="space-y-1">
                                    <div class="font-medium">{{ $currentMembership->seat->seat_number }}</div>
                                    <div class="text-xs text-zinc-500">{{ $currentMembership->seat?->room?->name ?? '-' }}</div>
                                </div>
                            @else
                                <flux:text class="text-sm text-zinc-500">{{ __('Not assigned') }}</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($currentMembership)
                                <div class="space-y-2">
                                    <flux:badge :color="$expiringSoon ? 'amber' : 'green'">
                                        {{ $expiringSoon ? __('Expiring') : __('Active') }}
                                    </flux:badge>
                                    <div class="text-xs text-zinc-500">
                                        {{ __('Valid until') }} {{ $currentMembership->end_date?->format('d M Y') ?? '-' }}
                                    </div>
                                </div>
                            @else
                                <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="whitespace-nowrap">
                            {{ $student->created_at->format('d M Y') }}
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:modal.trigger name="student-detail-modal">
                                    <flux:button size="sm" variant="outline" wire:click="show('{{ $student->id }}')">
                                        {{ __('View') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:modal.trigger name="edit-student-modal">
                                    <flux:button size="sm" wire:click="edit('{{ $student->id }}')">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:confirm="Are you sure you want to delete this student? This action cannot be undone."
                                    wire:click="delete('{{ $student->id }}')"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text>{{ __('No students found yet. Create the first admission to get started.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="create-student-modal" focusable class="w-full max-w-4xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Student Admission') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Add the student details, assign a room, select shifts, and allot an available seat in one flow.') }}
                </flux:text>
            </div>

            <form wire:submit="saveStudent" class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="student_name" label="Student Name" required />
                    <flux:input type="email" wire:model="student_email" label="Email" required />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input type="password" wire:model="student_password" label="Password (optional)" viewable />
                    <div class="rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <flux:heading size="sm">{{ __('Profile Image') }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Optional student profile photo.') }}</flux:text>
                            </div>

                            @if ($student_profile_image)
                                <img src="{{ $student_profile_image->temporaryUrl() }}" class="h-16 w-16 rounded-xl object-cover">
                            @endif
                        </div>

                        <div class="mt-4">
                            <input type="file" wire:model="student_profile_image" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <flux:select wire:model.live="form_library_id" label="Library" required>
                        <flux:select.option value="">{{ __('Select Library') }}</flux:select.option>
                        @foreach ($this->libraries as $library)
                            <flux:select.option value="{{ $library->id }}">{{ $library->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="form_room_id" label="Room" required>
                        <flux:select.option value="">{{ __('Select Room') }}</flux:select.option>
                        @foreach ($this->admissionRooms as $room)
                            <flux:select.option value="{{ $room->id }}">
                                {{ $room->name }} ({{ $room->type }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="amount" label="Price" readonly />
                </div>

                <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm">{{ __('Shifts') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Choose one or more shifts to check seat availability.') }}</flux:text>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        @forelse ($this->admissionShifts as $shift)
                            <label class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <input type="checkbox" wire:model.live="form_shift_ids" value="{{ $shift->id }}">
                                <span>{{ $shift->name }}</span>
                            </label>
                        @empty
                            <flux:text class="text-sm text-zinc-500">{{ __('Select a library to load shifts.') }}</flux:text>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <flux:heading size="sm">{{ __('Seat Selection') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Green seats are available, red are occupied, and amber are expiring soon.') }}</flux:text>
                        </div>

                        <flux:button type="button" size="sm" variant="outline" wire:click="recommendSeat">
                            {{ __('Auto Seat') }}
                        </flux:button>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 text-sm">
                        <span class="rounded-md bg-green-500 px-2 py-1 text-white">{{ __('Available') }}</span>
                        <span class="rounded-md bg-red-500 px-2 py-1 text-white">{{ __('Occupied') }}</span>
                        <span class="rounded-md bg-yellow-400 px-2 py-1 text-black">{{ __('Expiring') }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                        @forelse ($this->availableSeats as $seat)
                            <button
                                type="button"
                                @if (!$seat['occupied'] && $seat['is_active'])
                                    wire:click="$set('form_seat_id', '{{ $seat['id'] }}')"
                                @endif
                                class="rounded-xl px-3 py-3 text-center text-sm transition
                                    {{ $seat['occupied'] && !$seat['expiring'] ? 'bg-red-500 text-white' : '' }}
                                    {{ $seat['expiring'] ? 'bg-yellow-400 text-black' : '' }}
                                    {{ !$seat['occupied'] ? 'bg-green-500 text-white' : '' }}
                                    {{ !$seat['is_active'] ? 'bg-zinc-300 text-zinc-600' : '' }}
                                    {{ $form_seat_id == $seat['id'] ? 'ring-4 ring-blue-500' : '' }}">
                                {{ $seat['number'] }}
                            </button>
                        @empty
                            <flux:text class="text-sm text-zinc-500">{{ __('Select library, room, and shifts to load the seat layout.') }}</flux:text>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="resetCreateForm">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="submit">
                        {{ __('Create Admission') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="edit-student-modal" focusable class="w-full max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit Student') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Update the student profile, seat allocation, and membership setup here.') }}
                </flux:text>
            </div>

            <form wire:submit="updateStudent" class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="student_name" label="Student Name" required />
                    <flux:input type="email" wire:model="student_email" label="Email" required />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input type="password" wire:model="student_password" label="New Password (optional)" viewable />
                    <flux:input wire:model="amount" label="Price" readonly />
                </div>

                <div class="rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="sm">{{ __('Profile Image') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Upload a new image only if you want to replace the current one.') }}</flux:text>
                        </div>

                        @if ($student_profile_image)
                            <img src="{{ $student_profile_image->temporaryUrl() }}" class="h-16 w-16 rounded-xl object-cover">
                        @endif
                    </div>

                    <div class="mt-4">
                        <input type="file" wire:model="student_profile_image" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model.live="form_library_id" label="Library" required>
                        <flux:select.option value="">{{ __('Select Library') }}</flux:select.option>
                        @foreach ($this->libraries as $library)
                            <flux:select.option value="{{ $library->id }}">{{ $library->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="form_room_id" label="Room" required>
                        <flux:select.option value="">{{ __('Select Room') }}</flux:select.option>
                        @foreach ($this->admissionRooms as $room)
                            <flux:select.option value="{{ $room->id }}">
                                {{ $room->name }} ({{ $room->type }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                    <flux:heading size="sm">{{ __('Shifts') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Adjust shifts to refresh seat availability for this student.') }}</flux:text>

                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        @forelse ($this->admissionShifts as $shift)
                            <label class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <input type="checkbox" wire:model.live="form_shift_ids" value="{{ $shift->id }}">
                                <span>{{ $shift->name }}</span>
                            </label>
                        @empty
                            <flux:text class="text-sm text-zinc-500">{{ __('Select a library to load shifts.') }}</flux:text>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <flux:heading size="sm">{{ __('Seat Selection') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Update the seat using the same availability rules as student creation.') }}</flux:text>
                        </div>

                        <flux:button type="button" size="sm" variant="outline" wire:click="recommendSeat">
                            {{ __('Auto Seat') }}
                        </flux:button>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 text-sm">
                        <span class="rounded-md bg-green-500 px-2 py-1 text-white">{{ __('Available') }}</span>
                        <span class="rounded-md bg-red-500 px-2 py-1 text-white">{{ __('Occupied') }}</span>
                        <span class="rounded-md bg-yellow-400 px-2 py-1 text-black">{{ __('Expiring') }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                        @forelse ($this->availableSeats as $seat)
                            <button
                                type="button"
                                @if ((!$seat['occupied'] && $seat['is_active']) || $form_seat_id == $seat['id'])
                                    wire:click="$set('form_seat_id', '{{ $seat['id'] }}')"
                                @endif
                                class="rounded-xl px-3 py-3 text-center text-sm transition
                                    {{ $seat['occupied'] && !$seat['expiring'] ? 'bg-red-500 text-white' : '' }}
                                    {{ $seat['expiring'] ? 'bg-yellow-400 text-black' : '' }}
                                    {{ !$seat['occupied'] ? 'bg-green-500 text-white' : '' }}
                                    {{ !$seat['is_active'] ? 'bg-zinc-300 text-zinc-600' : '' }}
                                    {{ $form_seat_id == $seat['id'] ? 'ring-4 ring-blue-500' : '' }}">
                                {{ $seat['number'] }}
                            </button>
                        @empty
                            <flux:text class="text-sm text-zinc-500">{{ __('Select library, room, and shifts to load the seat layout.') }}</flux:text>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="resetEditForm">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="submit">
                        {{ __('Update Student') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="student-detail-modal" class="w-full max-w-3xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $this->selectedStudent?->name ?? __('Student Details') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Review student profile, library, and membership information.') }}
                </flux:text>
            </div>

            @if ($this->selectedStudent)
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                        <flux:text class="text-sm text-zinc-500">{{ __('Student') }}</flux:text>
                        <div class="mt-3 flex items-center gap-3">
                            <flux:avatar size="sm" src="{{ $this->selectedStudent->profile_image_url }}" />
                            <div>
                                <flux:heading size="sm">{{ $this->selectedStudent->name }}</flux:heading>
                                <flux:text class="text-sm">{{ $this->selectedStudent->email }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                        <flux:text class="text-sm text-zinc-500">{{ __('Library') }}</flux:text>
                        <flux:heading size="sm" class="mt-2">{{ $this->selectedStudent->library?->name ?? '-' }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ $this->selectedStudent->library?->city ?? __('City not set') }}</flux:text>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                        <flux:text class="text-sm text-zinc-500">{{ __('Memberships') }}</flux:text>
                        <flux:heading size="sm" class="mt-2">{{ $this->selectedStudent->memberships->count() }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('Total linked membership records') }}</flux:text>
                    </div>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Seat') }}</flux:table.column>
                        <flux:table.column>{{ __('Room') }}</flux:table.column>
                        <flux:table.column>{{ __('Shifts') }}</flux:table.column>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->selectedStudent->memberships as $membership)
                            @php
                                $isExpiring = $membership->status === 'active' && $membership->end_date && !$membership->end_date->isPast() && now()->diffInDays($membership->end_date, false) <= 3;
                            @endphp
                            <flux:table.row :key="$membership->id">
                                <flux:table.cell>{{ $membership->seat?->seat_number ?? '-' }}</flux:table.cell>
                                <flux:table.cell>{{ $membership->seat?->room?->name ?? '-' }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($membership->shifts as $shift)
                                            <flux:badge size="sm" color="zinc">{{ $shift->name }}</flux:badge>
                                        @empty
                                            <flux:text class="text-sm text-zinc-500">{{ __('No shifts linked') }}</flux:text>
                                        @endforelse
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>INR {{ number_format((float) $membership->amount, 2) }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="space-y-1">
                                        <flux:badge :color="$isExpiring ? 'amber' : ($membership->status === 'active' ? 'green' : 'zinc')">
                                            {{ $isExpiring ? __('Expiring') : ucfirst($membership->status) }}
                                        </flux:badge>
                                        <div class="text-xs text-zinc-500">
                                            {{ $membership->start_date?->format('d M Y') ?? '-' }} - {{ $membership->end_date?->format('d M Y') ?? '-' }}
                                        </div>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5">
                                    <flux:text>{{ __('No membership history found for this student.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text>{{ __('Select a student to view details.') }}</flux:text>
            @endif
        </div>
    </flux:modal>
</section>
