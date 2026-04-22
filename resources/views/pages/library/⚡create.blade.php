<?php

use Livewire\Component;
use App\Models\Library;
use App\Models\Shift;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithFileUploads;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $name = '';
    public $email = '';
    public $phone = '';
    public $whatsapp = '';
    public $state = '';
    public $city = '';
    public $address = '';
    public $google_map_link = '';
    public $profile_image = null;
    public $normal_price = 0;
    public $ac_price = 0;

    public $editingId = null;

    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

    // shift
    public $shiftLibraryId = null;
    public $shift_count = 1;
    public $shifts = [];

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function libraries()
    {
        return Library::where('user_id', auth()->id())
            ->tap(fn($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->paginate(5);
    }

    // 💾 Create / Update
    public function save()
    {
        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'phone' => 'required',
            'whatsapp' => 'required',
            'state' => 'required',
            'city' => 'required',
            'address' => 'required',
            'google_map_link' => 'nullable|url',
            'profile_image' => $this->editingId ? 'nullable|file|image|max:2048' : 'required|file|image|max:2048',
            'normal_price' => 'required|numeric|min:0',
            'ac_price' => 'required|numeric|min:0',
        ]);

        // 📸 Upload image
        $imagePath = null;
        if ($this->profile_image) {
            $imagePath = $this->profile_image->store('libraries', 'public');
        }

        if ($this->editingId) {
            // ✏️ UPDATE
            $library = Library::findOrFail($this->editingId);

            // if new image uploaded delete old one
            if ($imagePath && $library->profile_image) {
                Storage::disk('public')->delete($library->profile_image);
            }

            $library->update([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'whatsapp' => $this->whatsapp,
                'state' => $this->state,
                'city' => $this->city,
                'address' => $this->address,
                'google_map_link' => $this->google_map_link,
                'profile_image' => $imagePath ?? $library->profile_image,
                'normal_price' => $this->normal_price,
                'ac_price' => $this->ac_price,
            ]);
        } else {
            // ➕ CREATE
            $library = Library::create([
                'user_id' => auth()->id(),
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'whatsapp' => $this->whatsapp,
                'state' => $this->state,
                'city' => $this->city,
                'address' => $this->address,
                'google_map_link' => $this->google_map_link,
                'profile_image' => $imagePath,
                'normal_price' => $this->normal_price,
                'ac_price' => $this->ac_price,
            ]);
        }

        $this->dispatch('success', ['message' => $this->editingId ? 'Library updated successfully!' : 'Library created successfully!']);
        $this->resetForm();
    }

    // ✏️ Edit
    public function edit($id)
    {
        $library = Library::findOrFail($id);

        $this->editingId = $library->id;

        $this->name = $library->name;
        $this->email = $library->email;
        $this->phone = $library->phone;
        $this->whatsapp = $library->whatsapp;
        $this->state = $library->state;
        $this->city = $library->city;
        $this->address = $library->address;
        $this->google_map_link = $library->google_map_link;
        $this->normal_price = $library->normal_price;
        $this->ac_price = $library->ac_price;
    }

    // ❌ Delete
    public function delete($id)
    {
        Library::findOrFail($id)->delete();

        $this->dispatch('success', ['message' => 'Library deleted successfully!']);
    }

    // 🔄 Reset form
    public function resetForm()
    {
        $this->reset(['name', 'email', 'phone', 'whatsapp', 'state', 'city', 'address', 'google_map_link', 'profile_image', 'editingId']);
    }

    // shift
    // 🔥 Open shift modal
    public function openShiftModal($libraryId)
    {
        $this->shiftLibraryId = $libraryId;
        $this->loadShifts();
    }

    // 🔥 Load existing shifts
    public function loadShifts()
    {
        $this->shifts = Shift::where('library_id', $this->shiftLibraryId)
            ->get()
            ->map(
                fn($s) => [
                    'name' => $s->name,
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                ],
            )
            ->toArray();
    }

    // 🔥 Generate shifts
    public function generateShifts()
    {
        $library = Library::find($this->shiftLibraryId);
        if (!$library) {
            return;
        }

        $count = min($this->shift_count, 3);

        $open = strtotime($library->open_time);
        $close = strtotime($library->close_time);

        $total = $close - $open;
        $perShift = $total / $count;

        $this->shifts = [];

        for ($i = 0; $i < $count; $i++) {
            $start = $open + $i * $perShift;
            $end = $i == $count - 1 ? $close : $start + $perShift;

            $this->shifts[] = [
                'name' => 'Shift ' . ($i + 1),
                'start_time' => date('H:i', $start),
                'end_time' => date('H:i', $end),
            ];
        }
    }

    // 🔥 Save shifts
    public function saveShifts()
    {
        if (!$this->shiftLibraryId) {
            return;
        }

        // delete old shifts
        Shift::where('library_id', $this->shiftLibraryId)->delete();

        foreach ($this->shifts as $shift) {
            Shift::create([
                'library_id' => $this->shiftLibraryId,
                'name' => $shift['name'],
                'start_time' => $shift['start_time'],
                'end_time' => $shift['end_time'],
            ]);
        }

        $this->dispatch('success', ['message' => 'Shifts saved successfully']);
    }
};
?>

<!-- UI START -->
<section>
    <div class="relative mb-6 w-full">

        <div class="flex justify-between items-center">
            <div>
                <flux:heading size="xl" level="1">{{ __('Manage Libraries') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Create and manage your libraries') }}
                </flux:subheading>
            </div>
            <flux:modal.trigger name="create-library-modal">
                <flux:button x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-library-modal')">
                    {{ __('Create New Library') }}
                </flux:button>
            </flux:modal.trigger>
        </div>


        <flux:separator variant="subtle" />
    </div>

    <!-- LIST -->
    <div class="mt-10 space-y-4">

        <flux:table :paginate="$this->libraries">

            <!-- Columns -->
            <flux:table.columns>


                <flux:table.column>
                    Library
                </flux:table.column>

                <flux:table.column>
                    Contacts
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection"
                    wire:click="sort('created_at')">
                    Created
                </flux:table.column>


                <flux:table.column align="end">
                    Actions
                </flux:table.column>

            </flux:table.columns>

            <!-- Rows -->
            <flux:table.rows>

                @foreach ($this->libraries as $library)
                    <flux:table.row :key="$library->id">

                        <!-- Library -->
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:avatar size="xs" src="{{ $library->profile_image_url }}" />

                            <div>
                                <div class="font-semibold">
                                    {{ $library->name }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $library->city ?? '' }}
                                </div>
                            </div>

                        </flux:table.cell>

                        <!-- Contacts -->
                        <flux:table.cell>
                            <div class="space-y-1">
                                @if ($library->email)
                                    <div class="flex items-center gap-2 text-sm">
                                        <flux:icon.inbox class="size-4" />
                                        {{ $library->email }}
                                    </div>
                                @endif
                                @if ($library->phone)
                                    <div class="flex items-center gap-2 text-sm">
                                        <flux:icon.phone class="size-4" />
                                        {{ $library->phone }}
                                    </div>
                                @endif
                                @if ($library->whatsapp)
                                    <div class="flex items-center gap-2 text-sm">
                                        <flux:icon.chat-bubble-oval-left-ellipsis class="size-4" />
                                        {{ $library->whatsapp }}
                                    </div>
                                @endif
                            </div>
                        </flux:table.cell>

                        <!-- Created -->
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $library->created_at->format('d M Y') }}
                        </flux:table.cell>


                        <!-- Actions -->
                        <flux:table.cell align="end">
                            <div class="flex gap-2 justify-end">

                                <flux:modal.trigger name="shift-modal">
                                    <flux:button size="sm" variant="outline"
                                        wire:click="openShiftModal('{{ $library->id }}')">
                                        Shifts
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:modal.trigger name="create-library-modal">
                                    <flux:button size="sm" wire:click="edit('{{ $library->id }}')">
                                        Edit
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:button size="sm" variant="danger"
                                    wire:confirm="Are you sure you want to delete this library? This action cannot be undone."
                                    wire:click="delete('{{ $library->id }}')">
                                    Delete
                                </flux:button>

                            </div>
                        </flux:table.cell>

                    </flux:table.row>
                @endforeach

            </flux:table.rows>

        </flux:table>
    </div>

    <flux:modal name="create-library-modal" focusable class="w-full max-w-2xl">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <!-- FORM -->
            <form wire:submit="save" class="space-y-4">

                <flux:input wire:model="name" label="Library Name" required />

                <flux:input type="email" wire:model="email" label="Email" />

                <flux:input type="tel" wire:model="phone" label="Phone" />

                <flux:input type="tel" wire:model="whatsapp" label="WhatsApp" />

                <flux:input wire:model="state" label="State" />

                <flux:input wire:model="city" label="City" />

                <flux:textarea wire:model="address" label="Address" />

                <flux:input type="url" wire:model="google_map_link" label="Google Map Link (optional)" />

                <input type="file" wire:model="profile_image" />
                @if ($profile_image)
                    <img src="{{ $profile_image->temporaryUrl() }}" class="w-20 h-20 rounded">
                @endif

                <flux:input class="col-6" type="number" wire:model="normal_price" label="Normal Seat Price (₹)"
                    required />
                <flux:input class="col-6" type="number" wire:model="ac_price" label="AC Seat Price (₹)" required />

                <flux:button type="submit">
                    {{ $editingId ? 'Update Library' : 'Create Library' }}
                </flux:button>

            </form>
        </div>
    </flux:modal>

    <flux:modal name="shift-modal" class="w-full max-w-3xl">

        <div class="space-y-6">

            <flux:heading size="lg">Shift Management</flux:heading>

            <!-- Controls -->
            <div class="grid grid-cols-3 gap-4">

                <flux:select wire:model="shift_count" label="Number of Shifts">
                    <option value="1">1 Shift</option>
                    <option value="2">2 Shifts</option>
                    <option value="3">3 Shifts</option>
                </flux:select>

                <div class="flex items-end">
                    <flux:button wire:confirm="Are you sure to generate shifts? Previous shifts will be deleted"
                        wire:click="generateShifts">
                        Generate
                    </flux:button>
                </div>

            </div>

            <!-- Shift Table -->
            @if (count($shifts))

                <flux:table>

                    <flux:table.columns>
                        <flux:table.column>Shift</flux:table.column>
                        <flux:table.column>Start</flux:table.column>
                        <flux:table.column>End</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>

                        @foreach ($shifts as $i => $shift)
                            <flux:table.row>

                                <flux:table.cell>
                                    {{ $shift['name'] }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    {{ $shift['start_time'] }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    {{ $shift['end_time'] }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach

                    </flux:table.rows>

                </flux:table>

                <div class="flex justify-end">
                    <flux:button variant="filled" wire:click="saveShifts">
                        Save Shifts
                    </flux:button>
                </div>

            @endif

        </div>

    </flux:modal>
</section>
