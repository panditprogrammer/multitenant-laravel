<?php

use Livewire\Component;
use App\Models\Room;
use App\Models\Seat;

new class extends Component
{
    // 🏢 Room fields
    public $name = '';
    public $floor = '';
    public $is_active = true;
    public $editingId = null;

    // 💺 Seat generator
    public $room_id = '';
    public $prefix = 'A';
    public $start = 1;
    public $end = 10;
    public $type = 'NORMAL';

    // 📦 Rooms list
    public function getRoomsProperty()
    {
        return Room::with('seats')->latest()->get();
    }

    // 💾 Save Room
    public function saveRoom()
    {
        $this->validate([
            'name' => 'required|min:2',
            'floor' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($this->editingId) {
            Room::findOrFail($this->editingId)->update([
                'name' => $this->name,
                'floor' => $this->floor,
                'is_active' => $this->is_active,
            ]);
        } else {
            Room::create([
                'name' => $this->name,
                'floor' => $this->floor,
                'is_active' => $this->is_active,
            ]);
        }

        $this->reset(['name', 'floor', 'is_active', 'editingId']);
        $this->is_active = true; // reset default
    }

    // ✏️ Edit
    public function editRoom($id)
    {
        $room = Room::findOrFail($id);

        $this->editingId = $room->id;
        $this->name = $room->name;
        $this->floor = $room->floor;
        $this->is_active = $room->is_active;
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
            'type' => 'required|in:AC,NORMAL',
        ]);

        for ($i = $this->start; $i <= $this->end; $i++) {

            Seat::firstOrCreate([
                'room_id' => $this->room_id,
                'seat_number' => $this->prefix . $i,
            ], [
                'type' => $this->type,
                'is_active' => true,
            ]);
        }

        $this->reset(['prefix', 'start', 'end']);
        $this->prefix = 'A';
    }
};
?>

<section class="space-y-6">

    <flux:heading size="lg">Manage Rooms</flux:heading>

    <!-- 🏢 ROOM FORM -->
    <form wire:submit="saveRoom" class="grid grid-cols-4 gap-3">

        <flux:input wire:model="name" label="Room Name" required />

        <flux:input wire:model="floor" label="Floor" />

        <!-- is_active -->
        <select wire:model="is_active" class="border rounded p-2 mt-6">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>

        <flux:button type="submit" class="mt-6">
            {{ $editingId ? 'Update' : 'Create' }}
        </flux:button>

    </form>

    <!-- 💺 SEAT GENERATOR -->
    <div class="mt-6">

        <flux:heading size="md">Generate Seats</flux:heading>

        <form wire:submit="generateSeats" class="grid grid-cols-5 gap-3 mt-3">

            <select wire:model="room_id" class="border rounded p-2">
                <option value="">Select Room</option>
                @foreach($this->rooms as $room)
                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                @endforeach
            </select>

            <flux:input wire:model="prefix" label="Prefix" />

            <flux:input wire:model="start" label="Start" type="number" />

            <flux:input wire:model="end" label="End" type="number" />

            <select wire:model="type" class="border rounded p-2">
                <option value="NORMAL">Non AC</option>
                <option value="AC">AC</option>
            </select>

            <flux:button type="submit" class="col-span-5">
                Generate Seats
            </flux:button>

        </form>

    </div>

    <!-- 📊 FLUX TABLE -->
    <flux:table>

        <flux:table.columns>
            <flux:table.column>Room</flux:table.column>
            <flux:table.column>Floor</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Seats</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>

            @forelse ($this->rooms as $room)

                <flux:table.row :key="$room->id">

                    <!-- Room -->
                    <flux:table.cell>
                        <div class="font-semibold">{{ $room->name }}</div>
                    </flux:table.cell>

                    <!-- Floor -->
                    <flux:table.cell>
                        {{ $room->floor ?? '-' }}
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

                            <flux:button size="sm" wire:click="editRoom({{ $room->id }})">
                                Edit
                            </flux:button>

                            <flux:button size="sm" variant="danger" wire:click="deleteRoom({{ $room->id }})">
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