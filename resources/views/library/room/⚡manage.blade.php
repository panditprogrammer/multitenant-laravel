<?php

use App\Models\Library;
use App\Models\Membership;
use App\Models\Room;
use App\Models\Seat;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $name = '';
    public $floor = '';
    public $is_active = true;
    public $type = 'NORMAL';
    public $has_wifi = false;
    public $lib_id = '';
    public $editingId = null;

    public $room_id = '';
    public $prefix = 'A';
    public $start = 1;
    public $end = 10;
    public $seatOverviewRoomId = null;

    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

    protected function ownerId(): int
    {
        return auth()->user()->ownerAccountId();
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403);
    }

    public function startCreate()
    {
        $this->authorizePermission('create_room');
        $this->resetForm();
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
        return Library::where('user_id', $this->ownerId())
            ->withCount(['rooms', 'students', 'seats'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function rooms()
    {
        return Room::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->with(['library'])
            ->withCount('seats')
            ->tap(function ($query) {
                if ($this->sortBy === 'library') {
                    $query->join('libraries', 'libraries.id', '=', 'rooms.library_id')
                        ->orderBy('libraries.name', $this->sortDirection)
                        ->select('rooms.*');

                    return;
                }

                $query->orderBy($this->sortBy, $this->sortDirection);
            })
            ->paginate(10);
    }

    #[Computed]
    public function roomOptions()
    {
        return Room::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->with('library')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function roomStats()
    {
        $rooms = Room::whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->get();

        return [
            'normal_rooms' => $rooms->where('type', 'NORMAL')->count(),
            'ac_rooms' => $rooms->where('type', 'AC')->count(),
            'wifi_rooms' => $rooms->where('has_wifi', true)->count(),
            'active_rooms' => $rooms->where('is_active', true)->count(),
            'inactive_rooms' => $rooms->where('is_active', false)->count(),
        ];
    }

    #[Computed]
    public function seatOverviewRoom()
    {
        if (!$this->seatOverviewRoomId) {
            return null;
        }

        return Room::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->with(['library', 'seats' => fn ($query) => $query->orderBy('seat_number')])
            ->withCount('seats')
            ->find($this->seatOverviewRoomId);
    }

    #[Computed]
    public function seatOverviewSeats()
    {
        $room = $this->seatOverviewRoom;

        if (!$room) {
            return collect();
        }

        $memberships = Membership::query()
            ->where('library_id', $room->library_id)
            ->whereIn('seat_id', $room->seats->pluck('id'))
            ->where('status', 'active')
            ->whereDate('end_date', '>=', today())
            ->get()
            ->keyBy('seat_id');

        return $room->seats->map(function ($seat) use ($memberships) {
            $membership = $memberships->get($seat->id);
            $occupied = (bool) $membership;
            $expiring = $membership && now()->diffInDays($membership->end_date, false) <= 3;

            return [
                'id' => $seat->id,
                'seat_number' => $seat->seat_number,
                'is_active' => (bool) $seat->is_active,
                'occupied' => $occupied,
                'expiring' => $expiring,
                'status_label' => $expiring ? 'Expiring' : ($occupied ? 'Occupied' : 'Available'),
                'status_color' => $expiring ? 'amber' : ($occupied ? 'red' : 'green'),
                'end_date' => $membership?->end_date,
            ];
        });
    }

    public function saveRoom()
    {
        $this->authorizePermission($this->editingId ? 'edit_room' : 'create_room');

        $this->validate([
            'lib_id' => ['required', Rule::exists('libraries', 'id')->where(fn ($query) => $query->where('user_id', $this->ownerId()))],
            'name' => [
                'required',
                'min:2',
                Rule::unique('rooms', 'name')
                    ->ignore($this->editingId)
                    ->where(fn ($query) => $query->where('library_id', $this->lib_id)),
            ],
            'floor' => 'nullable|string|max:255',
            'type' => 'required|in:AC,NORMAL',
            'has_wifi' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $payload = [
            'name' => $this->name,
            'floor' => $this->floor,
            'has_wifi' => (bool) $this->has_wifi,
            'type' => $this->type,
            'is_active' => (bool) $this->is_active,
            'library_id' => $this->lib_id,
        ];

        if ($this->editingId) {
            Room::whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
                ->findOrFail($this->editingId)
                ->update($payload);
        } else {
            Room::create($payload);
        }

        $this->dispatch('success', ['message' => $this->editingId ? 'Room updated successfully!' : 'Room created successfully!']);
        $this->resetForm();
        $this->resetPage();
    }

    public function editRoom($id)
    {
        $this->authorizePermission('edit_room');
        $room = Room::whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->findOrFail($id);

        $this->editingId = $room->id;
        $this->name = $room->name;
        $this->floor = $room->floor;
        $this->has_wifi = (bool) $room->has_wifi;
        $this->type = $room->type;
        $this->is_active = (bool) $room->is_active;
        $this->lib_id = (string) $room->library_id;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function deleteRoom($id)
    {
        $this->authorizePermission('delete_room');
        Room::whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->findOrFail($id)
            ->delete();

        $this->dispatch('success', ['message' => 'Room deleted successfully!']);
        $this->resetPage();
    }

    public function generateSeats()
    {
        $this->authorizePermission('generate_seat');

        $this->validate([
            'room_id' => 'required|integer',
            'prefix' => 'required|string|max:2',
            'start' => 'required|integer|min:1',
            'end' => 'required|integer|gte:start',
        ]);

        $room = Room::whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->findOrFail($this->room_id);

        for ($i = $this->start; $i <= $this->end; $i++) {
            Seat::firstOrCreate(
                [
                    'room_id' => $room->id,
                    'seat_number' => strtoupper($this->prefix) . $i,
                ],
                [
                    'is_active' => true,
                ],
            );
        }

        $this->dispatch('success', ['message' => 'Seats generated successfully!']);

        $this->reset(['room_id', 'prefix', 'start', 'end']);
        $this->prefix = 'A';
        $this->start = 1;
        $this->end = 10;
    }

    public function openSeatOverview($id)
    {
        $this->authorizePermission('view_seat');
        $room = Room::whereHas('library', fn ($query) => $query->where('user_id', $this->ownerId()))
            ->findOrFail($id);

        $this->seatOverviewRoomId = $room->id;
    }

    public function resetForm()
    {
        $this->reset([
            'name',
            'floor',
            'type',
            'has_wifi',
            'is_active',
            'lib_id',
            'editingId',
        ]);

        $this->type = 'NORMAL';
        $this->is_active = true;
        $this->has_wifi = false;
        $this->lib_id = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }
};
?>

<section class="space-y-8">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Manage Rooms') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Create and manage rooms across your libraries') }}</flux:subheading>
            </div>

            @if (auth()->user()->can('create_room'))
                <flux:modal.trigger name="create-room-modal">
                    <flux:button wire:click="startCreate" x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-room-modal')">
                        {{ __('Create New Room') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Normal Rooms') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->roomStats['normal_rooms'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Rooms configured as non-AC') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('AC Rooms') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->roomStats['ac_rooms'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Rooms configured with AC') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('WiFi Rooms') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->roomStats['wifi_rooms'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Rooms with WiFi enabled') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Active Rooms') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->roomStats['active_rooms'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Rooms currently marked as active') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Inactive Rooms') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->roomStats['inactive_rooms'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Rooms currently marked as inactive') }}</flux:text>
        </div>
    </div>

    @if (auth()->user()->can('generate_seat'))
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Seat Generator') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        {{ __('Generate numbered seats for any room using a prefix and range.') }}
                    </flux:text>
                </div>
            </div>

            <form wire:submit="generateSeats" class="mt-6 grid gap-4 md:grid-cols-5">
                <flux:select wire:model="room_id" label="Select Room" required>
                    <flux:select.option value="">{{ __('Select Room') }}</flux:select.option>
                    @foreach ($this->roomOptions as $room)
                        <flux:select.option value="{{ $room->id }}">
                            {{ $room->name }} - {{ $room->library?->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="prefix" label="Prefix" maxlength="2" />
                <flux:input wire:model="start" label="Start" type="number" min="1" />
                <flux:input wire:model="end" label="End" type="number" min="1" />

                <div class="flex items-end">
                    <flux:button type="submit" class="w-full">
                        {{ __('Generate Seats') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <div class="space-y-4">
        <flux:table :paginate="$this->rooms">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">
                    {{ __('Room') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'library'" :direction="$sortDirection" wire:click="sort('library')">
                    {{ __('Library') }}
                </flux:table.column>
                <flux:table.column>{{ __('Details') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'seats_count'" :direction="$sortDirection" wire:click="sort('seats_count')">
                    {{ __('Seats') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                    {{ __('Created') }}
                </flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->rooms as $room)
                    <flux:table.row :key="$room->id">
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:avatar size="xs" src="{{ $room->library?->profile_image_url }}" />

                            <div>
                                <div class="font-semibold">{{ $room->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $room->floor ?: __('Floor not set') }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="font-medium">{{ $room->library?->name }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ $room->library?->city ?: __('City not set') }}
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <flux:badge :color="$room->type === 'AC' ? 'sky' : 'zinc'">
                                        {{ $room->type === 'AC' ? __('AC') : __('Normal') }}
                                    </flux:badge>

                                    @if ($room->has_wifi)
                                        <flux:badge color="emerald">{{ __('WiFi') }}</flux:badge>
                                    @endif
                                </div>

                                <div class="text-sm">
                                    <flux:badge :color="$room->is_active ? 'green' : 'red'">
                                        {{ $room->is_active ? __('Active') : __('Inactive') }}
                                    </flux:badge>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="font-semibold">{{ $room->seats_count }}</div>
                                <div class="text-xs text-zinc-500">{{ __('Generated seats in this room') }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="whitespace-nowrap">
                            {{ $room->created_at->format('d M Y') }}
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                @if (auth()->user()->can('view_seat'))
                                    <flux:modal.trigger name="seat-overview-modal">
                                        <flux:button size="sm" variant="outline" wire:click="openSeatOverview('{{ $room->id }}')">
                                            {{ __('View Seats') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endif

                                @if (auth()->user()->can('edit_room'))
                                    <flux:modal.trigger name="create-room-modal">
                                        <flux:button size="sm" wire:click="editRoom('{{ $room->id }}')">
                                            {{ __('Edit') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endif

                                @if (auth()->user()->can('delete_room'))
                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:confirm="Are you sure you want to delete this room? This action cannot be undone."
                                        wire:click="deleteRoom('{{ $room->id }}')"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text>{{ __('No rooms found yet. Create your first room to get started.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="create-room-modal" focusable class="w-full max-w-2xl">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Update Room') : __('Create New Room') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Add a room to a library, choose its type, and keep status details consistent across your setup.') }}
                </flux:text>
            </div>

            <form wire:submit="saveRoom" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="lib_id" label="Library" required>
                        <flux:select.option value="">{{ __('Select Library') }}</flux:select.option>
                        @forelse ($this->libraries as $library)
                            <flux:select.option value="{{ $library->id }}">{{ $library->name }}</flux:select.option>
                        @empty
                            <flux:select.option value="">{{ __('No libraries available') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <flux:input wire:model="name" label="Room Name" required />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="floor" label="Floor" />

                    <flux:select wire:model="type" label="Type">
                        <flux:select.option value="NORMAL">{{ __('Non AC') }}</flux:select.option>
                        <flux:select.option value="AC">{{ __('AC') }}</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="has_wifi" label="WiFi Availability">
                        <flux:select.option value="0">{{ __('Not Available') }}</flux:select.option>
                        <flux:select.option value="1">{{ __('Available') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="is_active" label="Room Status">
                        <flux:select.option value="1">{{ __('Active') }}</flux:select.option>
                        <flux:select.option value="0">{{ __('Inactive') }}</flux:select.option>
                    </flux:select>
                </div>

                <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                    <flux:text class="text-sm text-zinc-500">{{ __('Room Preview') }}</flux:text>
                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <div>
                            <flux:text class="text-sm">{{ __('Type') }}</flux:text>
                            <flux:heading size="sm">{{ $type === 'AC' ? __('AC') : __('Normal') }}</flux:heading>
                        </div>

                        <div>
                            <flux:text class="text-sm">{{ __('WiFi') }}</flux:text>
                            <flux:heading size="sm">{{ $has_wifi ? __('Available') : __('Not Available') }}</flux:heading>
                        </div>

                        <div>
                            <flux:text class="text-sm">{{ __('Status') }}</flux:text>
                            <flux:heading size="sm">{{ $is_active ? __('Active') : __('Inactive') }}</flux:heading>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="resetForm">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="submit">
                        {{ $editingId ? __('Update Room') : __('Create Room') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="seat-overview-modal" class="w-full max-w-3xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Seat Overview') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Review all generated seats for the selected room.') }}
                </flux:text>
            </div>

            @if ($this->seatOverviewRoom)
                <div class="flex flex-wrap gap-2">
                    <flux:badge color="green">{{ __('Available') }}</flux:badge>
                    <flux:badge color="red">{{ __('Occupied') }}</flux:badge>
                    <flux:badge color="amber">{{ __('Expiring') }}</flux:badge>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                        <flux:text class="text-sm text-zinc-500">{{ __('Room') }}</flux:text>
                        <flux:heading size="sm" class="mt-2">{{ $this->seatOverviewRoom->name }}</flux:heading>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                        <flux:text class="text-sm text-zinc-500">{{ __('Library') }}</flux:text>
                        <flux:heading size="sm" class="mt-2">{{ $this->seatOverviewRoom->library?->name }}</flux:heading>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                        <flux:text class="text-sm text-zinc-500">{{ __('Type') }}</flux:text>
                        <flux:heading size="sm" class="mt-2">{{ $this->seatOverviewRoom->type }}</flux:heading>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                        <flux:text class="text-sm text-zinc-500">{{ __('Total Seats') }}</flux:text>
                        <flux:heading size="sm" class="mt-2">{{ $this->seatOverviewRoom->seats_count }}</flux:heading>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    @if ($this->seatOverviewSeats->isNotEmpty())
                        <div class="flex flex-wrap gap-3">
                            @foreach ($this->seatOverviewSeats as $seat)
                                <div class="rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                                    <div class="flex items-center gap-2">
                                        <flux:badge :color="$seat['status_color']">
                                            {{ $seat['seat_number'] }}
                                        </flux:badge>

                                        @if (!$seat['is_active'])
                                            <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                                        @endif
                                    </div>

                                    <div class="mt-2 text-xs text-zinc-500">
                                        {{ __($seat['status_label']) }}
                                        @if ($seat['expiring'] && $seat['end_date'])
                                            {{ __('until') }} {{ $seat['end_date']->format('d M Y') }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <flux:text>{{ __('No seats generated for this room yet.') }}</flux:text>
                    @endif
                </div>
            @else
                <flux:text>{{ __('Select a room to view its seats.') }}</flux:text>
            @endif
        </div>
    </flux:modal>
</section>
