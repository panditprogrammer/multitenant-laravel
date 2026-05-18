<?php

use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new class extends Component {
    public $name = '';
    public $selectedPermissions = [];
    public $editingId = null;

    protected function ownerId(): int
    {
        return auth()->id();
    }

    public function mount()
    {
        abort_unless(auth()->user()->role === 'owner' && is_null(auth()->user()->owner_id), 403);
    }

    public function getPermissionsProperty()
    {
        return Permission::query()
            ->whereIn('name', PermissionRegistry::ownerPermissions())
            ->orderBy('name')
            ->get();
    }

    public function getRolesProperty()
    {
        return Role::query()
            ->where('owner_id', $this->ownerId())
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    public function saveRole()
    {
        $this->validate([
            'name' => [
                'required',
                'min:2',
                Rule::unique('roles', 'name')->ignore($this->editingId)->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
            'selectedPermissions' => 'required|array|min:1',
            'selectedPermissions.*' => ['required', Rule::exists('permissions', 'name')],
        ]);

        DB::transaction(function () {
            $role = Role::query()->updateOrCreate(
                ['id' => $this->editingId],
                [
                    'name' => $this->name,
                    'guard_name' => 'web',
                    'owner_id' => $this->ownerId(),
                ]
            );

            $role->syncPermissions($this->selectedPermissions);
        });

        $this->dispatch('success', ['message' => $this->editingId ? 'Role updated successfully!' : 'Role created successfully!']);
        $this->resetForm();
    }

    public function editRole($id)
    {
        $role = Role::query()->where('owner_id', $this->ownerId())->with('permissions')->findOrFail($id);

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
    }

    public function deleteRole($id)
    {
        $role = Role::query()->where('owner_id', $this->ownerId())->withCount('users')->findOrFail($id);

        if ($role->users_count > 0) {
            $this->addError('name', 'Remove users from this role before deleting it.');

            return;
        }

        $role->delete();
        $this->dispatch('success', ['message' => 'Role deleted successfully!']);
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['name', 'selectedPermissions', 'editingId']);
        $this->resetErrorBag();
        $this->resetValidation();
    }
};
?>

<section class="space-y-8">
    <div class="relative mb-6 w-full">
        <div>
            <flux:heading size="xl" level="1">{{ __('Manage Roles') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Create staff roles and select exactly which features they can access') }}</flux:subheading>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Role') : __('Create Role') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Permissions selected here control each staff login feature by feature.') }}</flux:text>
            </div>

            <form wire:submit="saveRole" class="mt-6 space-y-5">
                <flux:input wire:model="name" label="Role Name" required />

                <div class="space-y-3">
                    <flux:heading size="sm">{{ __('Permissions') }}</flux:heading>
                    @foreach ($this->permissions as $permission)
                        <label class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-800/80">
                            <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}">
                            <span>{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Reset') }}</flux:button>
                    <flux:button type="submit">{{ $editingId ? __('Update Role') : __('Create Role') }}</flux:button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 xl:col-span-2">
            <div>
                <flux:heading size="lg">{{ __('Created Roles') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('These roles can be assigned when creating staff logins.') }}</flux:text>
            </div>

            <div class="mt-6 space-y-4">
                @forelse ($this->roles as $role)
                    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <flux:heading size="sm">{{ $role->name }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500">{{ $role->users_count }} {{ __('users assigned') }}</flux:text>
                            </div>

                            <div class="flex gap-2">
                                <flux:button size="sm" wire:click="editRole('{{ $role->id }}')">{{ __('Edit') }}</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="deleteRole('{{ $role->id }}')">{{ __('Delete') }}</flux:button>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($role->permissions as $permission)
                                <flux:badge color="zinc">{{ $permission->name }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <flux:text class="rounded-xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-300">
                        {{ __('No custom roles created yet.') }}
                    </flux:text>
                @endforelse
            </div>
        </div>
    </div>
</section>
