<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new class extends Component {
    public $name = '';
    public $email = '';
    public $password = '';
    public $selectedRoleId = '';
    public $editingId = null;

    protected function ownerId(): int
    {
        return auth()->id();
    }

    public function mount()
    {
        abort_unless(auth()->user()->role === 'owner' && is_null(auth()->user()->owner_id), 403);
    }

    public function getRolesProperty()
    {
        return Role::query()->where('owner_id', $this->ownerId())->orderBy('name')->get();
    }

    public function getManagedUsersProperty()
    {
        return User::query()->where('owner_id', $this->ownerId())->with('roles')->latest()->get();
    }

    public function saveUser()
    {
        $this->validate([
            'name' => 'required|min:2',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'min:8'],
            'selectedRoleId' => ['required', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('owner_id', $this->ownerId()))],
        ]);

        $role = Role::query()->where('owner_id', $this->ownerId())->findOrFail($this->selectedRoleId);

        if ($this->editingId) {
            $user = User::query()->where('owner_id', $this->ownerId())->findOrFail($this->editingId);

            $payload = [
                'name' => $this->name,
                'email' => $this->email,
            ];

            if ($this->password) {
                $payload['password'] = Hash::make($this->password);
            }

            $user->update($payload);
            $user->syncRoles([$role]);
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => 'owner',
                'owner_id' => $this->ownerId(),
            ]);

            $user->assignRole($role);
        }

        $this->dispatch('success', ['message' => $this->editingId ? 'User updated successfully!' : 'User login created successfully!']);
        $this->resetForm();
    }

    public function editUser($id)
    {
        $user = User::query()->where('owner_id', $this->ownerId())->with('roles')->findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->selectedRoleId = (string) optional($user->roles->first())->id;
    }

    public function deleteUser($id)
    {
        User::query()->where('owner_id', $this->ownerId())->findOrFail($id)->delete();

        $this->dispatch('success', ['message' => 'User deleted successfully!']);
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['name', 'email', 'password', 'selectedRoleId', 'editingId']);
        $this->resetErrorBag();
        $this->resetValidation();
    }
};
?>

<section class="space-y-8">
    <div class="relative mb-6 w-full">
        <div>
            <flux:heading size="xl" level="1">{{ __('Manage Users') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Create multiple staff logins and assign a role to each one') }}</flux:subheading>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit User Login') : __('Create User Login') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Each staff login gets access from the selected role permissions.') }}</flux:text>
            </div>

            <form wire:submit="saveUser" class="mt-6 space-y-5">
                <flux:input wire:model="name" label="Name" required />
                <flux:input type="email" wire:model="email" label="Email" required />
                <flux:input type="password" wire:model="password" label="{{ $editingId ? __('New Password (optional)') : __('Password') }}" viewable />

                <flux:select wire:model="selectedRoleId" label="Role" required>
                    <flux:select.option value="">{{ __('Select Role') }}</flux:select.option>
                    @foreach ($this->roles as $role)
                        <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Reset') }}</flux:button>
                    <flux:button type="submit">{{ $editingId ? __('Update User') : __('Create User') }}</flux:button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 xl:col-span-2">
            <div>
                <flux:heading size="lg">{{ __('Staff Logins') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Only users created under this owner account are listed here.') }}</flux:text>
            </div>

            <div class="mt-6 space-y-4">
                @forelse ($this->managedUsers as $user)
                    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <flux:heading size="sm">{{ $user->name }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500">{{ $user->email }}</flux:text>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($user->roles as $role)
                                        <flux:badge color="zinc">{{ $role->name }}</flux:badge>
                                    @endforeach
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <flux:button size="sm" wire:click="editUser('{{ $user->id }}')">{{ __('Edit') }}</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="deleteUser('{{ $user->id }}')">{{ __('Delete') }}</flux:button>
                            </div>
                        </div>
                    </div>
                @empty
                    <flux:text class="rounded-xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-300">
                        {{ __('No owner-side user logins created yet.') }}
                    </flux:text>
                @endforelse
            </div>
        </div>
    </div>
</section>
