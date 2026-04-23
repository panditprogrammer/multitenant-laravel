<?php

use App\Models\Library;
use App\Models\Shift;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

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

    public $shiftLibraryId = null;
    public $shift_count = 1;
    public $shifts = [];

    public function startCreate()
    {
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
    }

    #[Computed]
    public function libraries()
    {
        return Library::where('user_id', auth()->id())
            ->withCount(['rooms', 'students', 'shifts'])
            ->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->paginate(5);
    }

    #[Computed]
    public function libraryStats()
    {
        $libraries = Library::where('user_id', auth()->id())
            ->withCount(['rooms', 'students', 'shifts'])
            ->get();

        return [
            'libraries' => $libraries->count(),
            'students' => $libraries->sum('students_count'),
            'rooms' => $libraries->sum('rooms_count'),
            'shifts' => $libraries->sum('shifts_count'),
        ];
    }

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

        $imagePath = null;
        if ($this->profile_image) {
            $imagePath = $this->profile_image->store('libraries', 'public');
        }

        if ($this->editingId) {
            $library = Library::where('user_id', auth()->id())->findOrFail($this->editingId);

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
            Library::create([
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

    public function edit($id)
    {
        $library = Library::where('user_id', auth()->id())->findOrFail($id);

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
        $this->profile_image = null;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function delete($id)
    {
        Library::where('user_id', auth()->id())->findOrFail($id)->delete();

        $this->dispatch('success', ['message' => 'Library deleted successfully!']);
    }

    public function resetForm()
    {
        $this->reset([
            'name',
            'email',
            'phone',
            'whatsapp',
            'state',
            'city',
            'address',
            'google_map_link',
            'profile_image',
            'normal_price',
            'ac_price',
            'editingId',
        ]);

        $this->normal_price = 0;
        $this->ac_price = 0;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function openShiftModal($libraryId)
    {
        Library::where('user_id', auth()->id())->findOrFail($libraryId);

        $this->shiftLibraryId = $libraryId;
        $this->shift_count = 1;
        $this->loadShifts();
    }

    public function loadShifts()
    {
        $this->shifts = Shift::where('library_id', $this->shiftLibraryId)
            ->get()
            ->map(fn ($shift) => [
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
            ])
            ->toArray();
    }

    public function generateShifts()
    {
        $library = Library::where('user_id', auth()->id())->find($this->shiftLibraryId);
        if (!$library) {
            return;
        }

        $count = min((int) $this->shift_count, 3);
        $open = strtotime((string) $library->open_time);
        $close = strtotime((string) $library->close_time);

        if ($count < 1 || $close <= $open) {
            return;
        }

        $total = $close - $open;
        $perShift = $total / $count;

        $this->shifts = [];

        for ($i = 0; $i < $count; $i++) {
            $start = $open + $i * $perShift;
            $end = $i === $count - 1 ? $close : $start + $perShift;

            $this->shifts[] = [
                'name' => 'Shift ' . ($i + 1),
                'start_time' => date('H:i', (int) $start),
                'end_time' => date('H:i', (int) $end),
            ];
        }
    }

    public function saveShifts()
    {
        if (!$this->shiftLibraryId) {
            return;
        }

        Library::where('user_id', auth()->id())->findOrFail($this->shiftLibraryId);

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

<section>
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Manage Libraries') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Create and manage your libraries') }}</flux:subheading>
            </div>

            <flux:modal.trigger name="create-library-modal">
                <flux:button wire:click="startCreate" x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-library-modal')">
                    {{ __('Create New Library') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Libraries') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->libraryStats['libraries'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Total libraries under your account') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Students') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->libraryStats['students'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Students assigned to your libraries') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Rooms') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->libraryStats['rooms'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Rooms created so far') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Shifts') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->libraryStats['shifts'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Shift plans configured') }}</flux:text>
        </div>
    </div>

    <div class="mt-10 space-y-4">
        <flux:table :paginate="$this->libraries">
            <flux:table.columns>
                <flux:table.column>{{ __('Library') }}</flux:table.column>
                <flux:table.column>{{ __('Contacts') }}</flux:table.column>
                <flux:table.column>{{ __('Pricing') }}</flux:table.column>
                <flux:table.column>{{ __('Setup') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                    {{ __('Created') }}
                </flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->libraries as $library)
                    <flux:table.row :key="$library->id">
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:avatar size="xs" src="{{ $library->profile_image_url }}" />

                            <div>
                                <div class="font-semibold">{{ $library->name }}</div>
                                <div class="text-xs text-gray-500">{{ $library->city ?? '' }}</div>
                            </div>
                        </flux:table.cell>

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

                        <flux:table.cell>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <flux:badge color="zinc">Normal</flux:badge>
                                    <span>INR {{ number_format((float) $library->normal_price, 2) }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <flux:badge color="sky">AC</flux:badge>
                                    <span>INR {{ number_format((float) $library->ac_price, 2) }}</span>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1 text-sm">
                                <div>{{ $library->rooms_count }} {{ __('rooms') }}</div>
                                <div>{{ $library->students_count }} {{ __('students') }}</div>
                                <div>{{ $library->shifts_count }} {{ __('shifts') }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="whitespace-nowrap">
                            {{ $library->created_at->format('d M Y') }}
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-2">
                                <flux:modal.trigger name="shift-modal">
                                    <flux:button size="sm" variant="outline" wire:click="openShiftModal('{{ $library->id }}')">
                                        {{ __('Shifts') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:modal.trigger name="create-library-modal">
                                    <flux:button size="sm" wire:click="edit('{{ $library->id }}')">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:confirm="Are you sure you want to delete this library? This action cannot be undone."
                                    wire:click="delete('{{ $library->id }}')"
                                >
                                    {{ __('Delete') }}
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
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Update Library') : __('Create New Library') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Add the main library details here. Rooms, students, and shifts can be managed right after this.') }}
                </flux:text>
            </div>

            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="name" label="Library Name" required />
                    <flux:input type="email" wire:model="email" label="Email" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input type="tel" wire:model="phone" label="Phone" />
                    <flux:input type="tel" wire:model="whatsapp" label="WhatsApp" />
                </div>

                <flux:textarea wire:model="address" label="Address" />

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="state" label="State" />
                    <flux:input wire:model="city" label="City" />
                </div>

                <flux:input type="url" wire:model="google_map_link" label="Google Map Link (optional)" />

                <div class="rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="sm">{{ __('Library Photo') }}</flux:heading>
                            <flux:text class="mt-1 text-sm text-zinc-500">
                                {{ __('Upload a clear cover image for the library profile.') }}
                            </flux:text>
                        </div>

                        @if ($profile_image)
                            <img src="{{ $profile_image->temporaryUrl() }}" class="h-20 w-20 rounded-xl object-cover">
                        @endif
                    </div>

                    <div class="mt-4">
                        <input type="file" wire:model="profile_image" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input type="number" step="0.01" min="0" wire:model="normal_price" label="Normal Seat Price (INR)" required />
                    <flux:input type="number" step="0.01" min="0" wire:model="ac_price" label="AC Seat Price (INR)" required />
                </div>

                <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                    <flux:text class="text-sm text-zinc-500">{{ __('Pricing Preview') }}</flux:text>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div>
                            <flux:text class="text-sm">{{ __('Normal') }}</flux:text>
                            <flux:heading size="sm">INR {{ number_format((float) $normal_price, 2) }}</flux:heading>
                        </div>
                        <div>
                            <flux:text class="text-sm">{{ __('AC') }}</flux:text>
                            <flux:heading size="sm">INR {{ number_format((float) $ac_price, 2) }}</flux:heading>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="resetForm">
                        {{ __('Reset') }}
                    </flux:button>

                    <flux:button type="submit">
                        {{ $editingId ? __('Update Library') : __('Create Library') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="shift-modal" class="w-full max-w-3xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Shift Management') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Generate and save shift blocks for the selected library.') }}
                </flux:text>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <flux:select wire:model="shift_count" label="Number of Shifts">
                    <option value="1">1 Shift</option>
                    <option value="2">2 Shifts</option>
                    <option value="3">3 Shifts</option>
                </flux:select>

                <div class="flex items-end">
                    <flux:button wire:confirm="Are you sure to generate shifts? Previous shifts will be deleted" wire:click="generateShifts">
                        {{ __('Generate') }}
                    </flux:button>
                </div>
            </div>

            @if (count($shifts))
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Shift') }}</flux:table.column>
                        <flux:table.column>{{ __('Start') }}</flux:table.column>
                        <flux:table.column>{{ __('End') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($shifts as $index => $shift)
                            <flux:table.row :key="$index">
                                <flux:table.cell>{{ $shift['name'] }}</flux:table.cell>
                                <flux:table.cell>{{ $shift['start_time'] }}</flux:table.cell>
                                <flux:table.cell>{{ $shift['end_time'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                <div class="flex justify-end">
                    <flux:button variant="filled" wire:click="saveShifts">
                        {{ __('Save Shifts') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
</section>
