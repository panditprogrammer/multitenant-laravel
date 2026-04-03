<?php

use Livewire\Component;
use App\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;
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
    public $domain = '';
    public $profile_image = null;

    public $editingId = null;

    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

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
        return Tenant::with('domains')
            ->where('owner_id', auth()->id())
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
            'domain' => 'required|unique:domains,domain,' . ($this->editingId ?? 'NULL') . ',tenant_id',
            'profile_image' => $this->editingId ? 'nullable|file|image|max:2048' : 'required|file|image|max:2048',
        ]);

        // 📸 Upload image
        $imagePath = null;
        if ($this->profile_image) {
            $imagePath = $this->profile_image->store('libraries', 'public');
        }

        if ($this->editingId) {
            // ✏️ UPDATE
            $tenant = Tenant::findOrFail($this->editingId);

            // if new image uploaded delete old one
            if ($imagePath && $tenant->profile_image) {
                Storage::disk('public')->delete($tenant->profile_image);
            }

            $tenant->update([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'whatsapp' => $this->whatsapp,
                'state' => $this->state,
                'city' => $this->city,
                'address' => $this->address,
                'google_map_link' => $this->google_map_link,
                'profile_image' => $imagePath ?? $tenant->profile_image,
            ]);

            Domain::where('tenant_id', $tenant->id)->update([
                'domain' => $this->domain,
            ]);
        } else {
            // ➕ CREATE
            $tenant = Tenant::create([
                'owner_id' => auth()->id(),
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'whatsapp' => $this->whatsapp,
                'state' => $this->state,
                'city' => $this->city,
                'address' => $this->address,
                'google_map_link' => $this->google_map_link,
                'profile_image' => $imagePath,
            ]);

            Domain::create([
                'domain' => $this->domain,
                'tenant_id' => $tenant->id,
            ]);

            // 🚨 OUTSIDE transaction
            $tenant->run(function () {
                \Artisan::call('migrate');
            });
        }

        $this->dispatch('success', ['message' => $this->editingId ? 'Library updated successfully!' : 'Library created successfully!']);
        $this->resetForm();
    }

    // ✏️ Edit
    public function edit($id)
    {
        $tenant = Tenant::findOrFail($id);

        $this->editingId = $tenant->id;

        $this->name = $tenant->name;
        $this->email = $tenant->email;
        $this->phone = $tenant->phone;
        $this->whatsapp = $tenant->whatsapp;
        $this->state = $tenant->state;
        $this->city = $tenant->city;
        $this->address = $tenant->address;
        $this->google_map_link = $tenant->google_map_link;

        $domain = Domain::where('tenant_id', $tenant->id)->first();
        $this->domain = $domain->domain ?? '';
    }

    // ❌ Delete
    public function delete($id)
    {
        Tenant::findOrFail($id)->delete();

        $this->dispatch('library-deleted');
    }

    // 🔄 Reset form
    public function resetForm()
    {
        $this->reset(['name', 'email', 'phone', 'whatsapp', 'state', 'city', 'address', 'google_map_link', 'domain', 'profile_image', 'editingId']);
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

                <flux:table.column>
                    Domain
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

                        <!-- Domain -->
                        <flux:table.cell>
                            {{ $library->domains->first()->domain ?? '-' }}
                        </flux:table.cell>

                        <!-- Actions -->
                        <flux:table.cell align="end">
                            <div class="flex gap-2 justify-end">

                                <flux:button size="sm" variant="ghost"
                                    href="{{ $library->domains->first()->domain ? 'http://' . $library->domains->first()->domain . '.' . config('tenancy.central_domains')[0] : '#' }}"
                                    target="_blank">
                                    Open
                                </flux:button>

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

                <flux:heading>Your custom domain</flux:heading>
                <flux:input.group>
                    <flux:input wire:model="domain" placeholder="library" required />
                    <flux:input.group.suffix>.{{ config('tenancy.central_domains')[1] ?? 'yourdomain.com' }}
                    </flux:input.group.suffix>
                </flux:input.group>

                <input type="file" wire:model="profile_image" />
                @if ($profile_image)
                    <img src="{{ $profile_image->temporaryUrl() }}" class="w-20 h-20 rounded">
                @endif


                <flux:button type="submit">
                    {{ $editingId ? 'Update Library' : 'Create Library' }}
                </flux:button>

            </form>
        </div>
    </flux:modal>
</section>
