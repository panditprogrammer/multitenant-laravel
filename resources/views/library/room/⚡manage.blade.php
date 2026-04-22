<?php

use Livewire\Component;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Library;

new class extends Component {
    // 🏢 Room fields
    public $name = '';
    public $floor = '';
    public $is_active = true;
    public $type = 'NORMAL';
    public $has_wifi = false;
    public $editingId = null;

    // 💺 Seat generator
    public $room_id = '';
    public $prefix = 'A';
    public $start = 1;
    public $end = 10;

    public $lib_id = null;

    // 📦 Rooms list
    public function getRoomsProperty()
    {
        return Room::with('seats')->latest()->get();
    }

    // 📦 Libraries list
    public function getLibrariesProperty()
    {
        return Library::latest()->get();
    }

    // 💾 Save Room
    public function saveRoom()
    {
        $this->validate([
            'name' => 'required|min:2',
            'floor' => 'nullable|string',
            'is_active' => 'boolean',
            'lib_id' => 'required|exists:libraries,id',
            'type' => 'required|in:AC,NORMAL',
            'has_wifi' => 'boolean'
        ]);

        if ($this->editingId) {
            Room::findOrFail($this->editingId)->update([
                'name' => $this->name,
                'floor' => $this->floor,
                'has_wifi' => $this->has_wifi,
                'type' => $this->type,
                'is_active' => $this->is_active,
                'library_id' => $this->lib_id,
            ]);
        } else {
            Room::create([
                'name' => $this->name,
                'floor' => $this->floor,
                'has_wifi' => $this->has_wifi,
                'type' => $this->type,
                'is_active' => $this->is_active,
                'library_id' => $this->lib_id,
            ]);
        }

        $this->reset(['name', 'floor','type','has_wifi', 'is_active', 'editingId']);
        $this->is_active = true; // reset default
    }

    // ✏️ Edit
    public function editRoom($id)
    {
        $room = Room::findOrFail($id);

        $this->editingId = $room->id;
        $this->name = $room->name;
        $this->floor = $room->floor;
        $this->has_wifi = $room->has_wifi;
        $this->type = $room->type;
        $this->is_active = $room->is_active;
        $this->lib_id = $room->library_id;
    }

    // ❌ Delete
    public function deleteRoom($id)
    {
        Room::findOrFail($id)->delete();
    }

    // 🔥 Generate Seats
    public function generateSeats()
    {
        $this->validate([
            'room_id' => 'required|exists:rooms,id',
            'prefix' => 'required|string|max:2',
            'start' => 'required|integer|min:1',
            'end' => 'required|integer|gte:start',
        ]);

        for ($i = $this->start; $i <= $this->end; $i++) {
            Seat::firstOrCreate(
                [
                    'room_id' => $this->room_id,
                    'seat_number' => strtoupper($this->prefix) . $i,
                ],
                [
                    'is_active' => true,
                ],
            );
        }

        $this->reset(['prefix', 'start', 'end']);
        $this->prefix = 'A';
    }
};
?>

<section class="space-y-6">

    <flux:heading size="lg">Manage Rooms</flux:heading>

    <!-- 🏢 ROOM FORM -->
    <form wire:submit="saveRoom" class="grid md:grid-cols-4 gap-3">

        <flux:select wire:model="lib_id" label="Library" required>
            <flux:select.option value="">Select Library</flux:select.option>
            @forelse ($this->libraries as $lib)
                <flux:select.option value="{{ $lib->id }}">{{ $lib->name }}</flux:select.option>
            @empty
                <flux:select.option value="">No libraries available</flux:select.option>
            @endforelse
        </flux:select>

        <flux:input wire:model="name" label="Room Name" required />

        <flux:input wire:model="floor" label="Floor" />

         <flux:select wire:model="type" label="Type">
            <flux:select.option value="NORMAL">Non AC</flux:select.option>
            <flux:select.option value="AC">AC</flux:select.option>
        </flux:select>

        <flux:select wire:model="has_wifi" label="Has Wifi">
            <flux:select.option value="0">Not avaiable</flux:select.option>
            <flux:select.option value="1">Available</flux:select.option>
        </flux:select>
 <!-- is_active -->
        <flux:select wire:model="is_active" class="mt-6">
            <flux:select.option value="1">Active</flux:select.option>
            <flux:select.option value="0">Inactive</flux:select.option>
        </flux:select>

        <flux:button type="submit" variant="primary" class="mt-6">
            {{ $editingId ? 'Update' : 'Create' }}
        </flux:button>

    </form>

    <!-- 💺 SEAT GENERATOR -->
    <flux:heading size="lg">Generate Seats</flux:heading>

    <form wire:submit="generateSeats" class="grid md:grid-cols-5 gap-3">

        <flux:select wire:model="room_id" label="Select Room" required>
            <flux:select.option value="">Select Room</flux:select.option>
            @foreach ($this->rooms as $room)
                <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="prefix" label="Prefix" />

        <flux:input wire:model="start" label="Start" type="number" />

        <flux:input wire:model="end" label="End" type="number" />

        <flux:button type="submit" variant="primary" class="md:col-span-2 mt-6">
            Generate Seats
        </flux:button>

    </form>

    <!-- 📊 FLUX TABLE -->
    <flux:table>

        <flux:table.columns>
            <flux:table.column>Room</flux:table.column>
            <flux:table.column>Floor</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Seats</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>

            @forelse ($this->rooms as $room)

                <flux:table.row :key="$room->id">

                    <!-- Room -->
                    <flux:table.cell class="flex items-center gap-3">
                        <flux:avatar size="xs" src="{{ $room->library?->profile_image_url }}" />
                        {{ $room->name }}
                    </flux:table.cell>

                    <!-- Floor -->
                    <flux:table.cell>
                        {{ $room->floor ?? '-' }}
                    </flux:table.cell>

                      <flux:table.cell>
                        {{ $room->type ?? '-' }}   {{ $room->has_wifi ? '+ Wifi' : '' }}
                    </flux:table.cell>

                    <!-- Status -->
                    <flux:table.cell>
                        <flux:badge :color="$room->is_active ? 'green' : 'red'">
                            {{ $room->is_active ? 'Active' : 'Inactive' }}
                        </flux:badge>
                    </flux:table.cell>

                    <!-- Seats -->
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-1">

                            @forelse($room->seats as $seat)
                                <flux:badge size="sm">
                                    {{ $seat->seat_number }}
                                </flux:badge>

                            @empty
                                <flux:text size="sm">No seats</flux:text>
                            @endforelse

                        </div>
                    </flux:table.cell>

                    <!-- Actions -->
                    <flux:table.cell align="end">

                        <div class="flex gap-2 justify-end">

                            <flux:button size="sm" variant="ghost" wire:click="editRoom({{ $room->id }})">
                                Edit
                            </flux:button>

                            <flux:button size="sm" variant="danger"
                                wire:confirm="Are you sure you want to delete this room?"
                                wire:click="deleteRoom({{ $room->id }})">
                                Delete
                            </flux:button>

                        </div>

                    </flux:table.cell>

                </flux:table.row>

            @empty

                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text>No rooms found.</flux:text>
                    </flux:table.cell>
                </flux:table.row>

            @endforelse

        </flux:table.rows>

    </flux:table>

</section>
