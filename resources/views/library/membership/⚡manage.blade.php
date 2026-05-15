<?php

use App\Models\Library;
use App\Models\Membership;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $filter_library_id = '';
    public $filter_status = '';
    public $filter_payment = '';

    public $editingMembershipId = null;
    public $start_date = '';
    public $end_date = '';
    public $amount = 0;
    public $status = 'active';
    public $mark_cash_paid = false;
    public $paid_at = '';

    public $sortBy = 'start_date';
    public $sortDirection = 'desc';

    public function mount($library = null)
    {
        if ($library && (string) $library !== '0') {
            $libraryId = (string) $library;

            if ($this->libraries->pluck('id')->map(fn ($id) => (string) $id)->contains($libraryId)) {
                $this->filter_library_id = $libraryId;
            }
        }
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
    public function memberships()
    {
        return Membership::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->with(['user', 'seat.room', 'library', 'shifts'])
            ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
            ->when($this->filter_status, fn ($query) => $query->where('status', $this->filter_status))
            ->when($this->filter_payment, function ($query) {
                if ($this->filter_payment === 'paid') {
                    $query->whereNotNull('paid_at');
                }

                if ($this->filter_payment === 'pending') {
                    $query->whereNull('paid_at');
                }
            })
            ->tap(function ($query) {
                if ($this->sortBy === 'library') {
                    $query->join('libraries', 'libraries.id', '=', 'memberships.library_id')
                        ->orderBy('libraries.name', $this->sortDirection)
                        ->select('memberships.*');

                    return;
                }

                if ($this->sortBy === 'student') {
                    $query->join('users', 'users.id', '=', 'memberships.user_id')
                        ->orderBy('users.name', $this->sortDirection)
                        ->select('memberships.*');

                    return;
                }

                $query->orderBy($this->sortBy, $this->sortDirection);
            })
            ->paginate(10);
    }

    #[Computed]
    public function membershipStats()
    {
        $memberships = Membership::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->get();

        return [
            'active' => $memberships->filter(fn ($membership) => $membership->status === 'active' && $membership->end_date && !$membership->end_date->isPast())->count(),
            'expiring' => $memberships->filter(fn ($membership) => $membership->status === 'active' && $membership->end_date && !$membership->end_date->isPast() && now()->diffInDays($membership->end_date, false) <= 3)->count(),
            'cash_paid' => $memberships->where('payment_method', 'cash')->whereNotNull('paid_at')->count(),
            'pending_payment' => $memberships->whereNull('paid_at')->count(),
        ];
    }

    public function updatedFilterLibraryId()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    public function updatedFilterPayment()
    {
        $this->resetPage();
    }

    public function edit($id)
    {
        $membership = Membership::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->with(['user', 'seat.room', 'library', 'shifts'])
            ->findOrFail($id);

        $this->editingMembershipId = $membership->id;
        $this->start_date = $membership->start_date?->format('Y-m-d') ?? '';
        $this->end_date = $membership->end_date?->format('Y-m-d') ?? '';
        $this->amount = (float) $membership->amount;
        $this->status = $membership->status;
        $this->mark_cash_paid = (bool) ($membership->payment_method === 'cash' && $membership->paid_at);
        $this->paid_at = $membership->paid_at?->format('Y-m-d\TH:i') ?? '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function saveMembership()
    {
        $this->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:active,expired,cancelled',
            'paid_at' => $this->mark_cash_paid ? 'nullable|date' : 'nullable',
        ]);

        $membership = Membership::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->findOrFail($this->editingMembershipId);

        $membership->update([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'amount' => $this->amount,
            'status' => $this->status,
            'payment_method' => $this->mark_cash_paid ? 'cash' : null,
            'paid_at' => $this->mark_cash_paid
                ? ($this->paid_at ? Carbon::parse($this->paid_at) : now())
                : null,
        ]);

        $this->dispatch('success', ['message' => $this->mark_cash_paid ? 'Membership updated and cash payment collected!' : 'Membership updated successfully!']);
        $this->resetMembershipForm();
    }

    public function resetMembershipForm()
    {
        $this->reset([
            'editingMembershipId',
            'start_date',
            'end_date',
            'amount',
            'status',
            'mark_cash_paid',
            'paid_at',
        ]);

        $this->amount = 0;
        $this->status = 'active';
        $this->mark_cash_paid = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }
};
?>

<section class="space-y-8">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Manage Memberships') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Track active plans, update dates, and collect owner-side cash payments') }}</flux:subheading>
            </div>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Active Memberships') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->membershipStats['active'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Memberships currently running') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Expiring Soon') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->membershipStats['expiring'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Plans ending within the next 3 days') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Cash Collected') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->membershipStats['cash_paid'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Memberships already marked as paid by cash') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Pending Payment') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->membershipStats['pending_payment'] }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Memberships still waiting for payment collection') }}</flux:text>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div>
            <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Filter memberships by library, status, and payment state.') }}</flux:text>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <flux:select wire:model.live="filter_library_id" label="Library">
                <flux:select.option value="">{{ __('All Libraries') }}</flux:select.option>
                @foreach ($this->libraries as $library)
                    <flux:select.option value="{{ $library->id }}">{{ $library->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter_status" label="Status">
                <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="expired">{{ __('Expired') }}</flux:select.option>
                <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="filter_payment" label="Payment">
                <flux:select.option value="">{{ __('All Payments') }}</flux:select.option>
                <flux:select.option value="paid">{{ __('Cash Collected') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Pending Payment') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="space-y-4">
        <flux:table :paginate="$this->memberships">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'student'" :direction="$sortDirection" wire:click="sort('student')">
                    {{ __('Student') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'library'" :direction="$sortDirection" wire:click="sort('library')">
                    {{ __('Library') }}
                </flux:table.column>
                <flux:table.column>{{ __('Seat & Shift') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'start_date'" :direction="$sortDirection" wire:click="sort('start_date')">
                    {{ __('Dates') }}
                </flux:table.column>
                <flux:table.column>{{ __('Payment') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->memberships as $membership)
                    @php
                        $isExpiring = $membership->status === 'active' && $membership->end_date && !$membership->end_date->isPast() && now()->diffInDays($membership->end_date, false) <= 3;
                        $isPaid = (bool) $membership->paid_at;
                    @endphp
                    <flux:table.row :key="$membership->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="xs" src="{{ $membership->user?->profile_image_url }}" />
                                <div>
                                    <div class="font-semibold">{{ $membership->user?->name ?? '-' }}</div>
                                    <div class="text-xs text-zinc-500">{{ $membership->user?->email ?? '-' }}</div>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="font-medium">{{ $membership->library?->name ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $membership->library?->city ?? __('City not set') }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-2">
                                <div class="font-medium">
                                    {{ $membership->seat?->seat_number ?? '-' }}
                                    @if ($membership->seat?->room)
                                        <span class="text-xs text-zinc-500">{{ __('in') }} {{ $membership->seat->room->name }}</span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-1">
                                    @forelse ($membership->shifts as $shift)
                                        <flux:badge size="sm" color="zinc">{{ $shift->name }}</flux:badge>
                                    @empty
                                        <flux:text class="text-sm text-zinc-500">{{ __('No shifts linked') }}</flux:text>
                                    @endforelse
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-2">
                                <div class="text-sm">
                                    {{ $membership->start_date?->format('d M Y') ?? '-' }} - {{ $membership->end_date?->format('d M Y') ?? '-' }}
                                </div>

                                <flux:badge :color="$isExpiring ? 'amber' : ($membership->status === 'active' ? 'green' : 'zinc')">
                                    {{ $isExpiring ? __('Expiring') : ucfirst($membership->status) }}
                                </flux:badge>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-2">
                                <div class="font-semibold">INR {{ number_format((float) $membership->amount, 2) }}</div>
                                <div class="flex flex-wrap gap-2">
                                    <flux:badge :color="$isPaid ? 'green' : 'amber'">
                                        {{ $isPaid ? __('Cash Paid') : __('Pending') }}
                                    </flux:badge>

                                    @if ($membership->paid_at)
                                        <span class="text-xs text-zinc-500">{{ $membership->paid_at->format('d M Y h:i A') }}</span>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <flux:modal.trigger name="membership-manage-modal">
                                <flux:button size="sm" wire:click="edit('{{ $membership->id }}')">
                                    {{ __('Manage') }}
                                </flux:button>
                            </flux:modal.trigger>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text>{{ __('No memberships found for the current filters.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="membership-manage-modal" focusable class="w-full max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Manage Membership') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Adjust the membership period and record cash collection from the owner side.') }}
                </flux:text>
            </div>

            <form wire:submit="saveMembership" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input type="date" wire:model="start_date" label="Start Date" required />
                    <flux:input type="date" wire:model="end_date" label="End Date" required />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input type="number" step="0.01" min="0" wire:model="amount" label="Amount (INR)" required />

                    <flux:select wire:model="status" label="Status">
                        <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                        <flux:select.option value="expired">{{ __('Expired') }}</flux:select.option>
                        <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                    </flux:select>
                </div>

                <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/80">
                    <div class="flex items-center gap-3">
                        <input id="mark_cash_paid" type="checkbox" wire:model.live="mark_cash_paid">
                        <label for="mark_cash_paid" class="text-sm font-medium">{{ __('Collect payment by cash') }}</label>
                    </div>

                    <flux:text class="mt-2 text-sm text-zinc-500">
                        {{ __('Use this when the owner receives payment offline. Student-side gateway support can be added later without changing this flow.') }}
                    </flux:text>

                    @if ($mark_cash_paid)
                        <div class="mt-4">
                            <flux:input type="datetime-local" wire:model="paid_at" label="Cash Collected At" />
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text class="text-sm text-zinc-500">{{ __('Preview') }}</flux:text>
                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <div>
                            <flux:text class="text-sm">{{ __('Amount') }}</flux:text>
                            <flux:heading size="sm">INR {{ number_format((float) $amount, 2) }}</flux:heading>
                        </div>

                        <div>
                            <flux:text class="text-sm">{{ __('Status') }}</flux:text>
                            <flux:heading size="sm">{{ ucfirst($status) }}</flux:heading>
                        </div>

                        <div>
                            <flux:text class="text-sm">{{ __('Payment') }}</flux:text>
                            <flux:heading size="sm">{{ $mark_cash_paid ? __('Cash Collected') : __('Pending') }}</flux:heading>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="resetMembershipForm">
                            {{ __('Cancel') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="submit">
                        {{ __('Save Membership') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
