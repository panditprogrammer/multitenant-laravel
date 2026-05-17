<?php

use App\Models\Library;
use App\Models\Payment;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $filter_library_id = '';
    public $filter_status = '';
    public $filter_method = '';
    public $search = '';
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

        $this->resetPage();
    }

    #[Computed]
    public function libraries()
    {
        return Library::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function payments()
    {
        return Payment::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->with(['user', 'library', 'membership.seat.room'])
            ->when($this->filter_library_id, fn ($query) => $query->where('library_id', $this->filter_library_id))
            ->when($this->filter_status, fn ($query) => $query->where('status', $this->filter_status))
            ->when($this->filter_method, fn ($query) => $query->where('payment_method', $this->filter_method))
            ->when($this->search, function ($query) {
                $search = trim($this->search);

                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery
                        ->where('reference', 'like', "%{$search}%")
                        ->orWhere('razorpay_order_id', 'like', "%{$search}%")
                        ->orWhere('razorpay_payment_id', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('library', fn ($libraryQuery) => $libraryQuery
                            ->where('name', 'like', "%{$search}%"));
                });
            })
            ->tap(function ($query) {
                if ($this->sortBy === 'student') {
                    $query->join('users', 'users.id', '=', 'payments.user_id')
                        ->orderBy('users.name', $this->sortDirection)
                        ->select('payments.*');

                    return;
                }

                if ($this->sortBy === 'library') {
                    $query->join('libraries', 'libraries.id', '=', 'payments.library_id')
                        ->orderBy('libraries.name', $this->sortDirection)
                        ->select('payments.*');

                    return;
                }

                $query->orderBy($this->sortBy, $this->sortDirection);
            })
            ->paginate(10);
    }

    #[Computed]
    public function paymentStats()
    {
        $payments = Payment::query()
            ->whereHas('library', fn ($query) => $query->where('user_id', auth()->id()))
            ->get();

        return [
            'total' => $payments->count(),
            'paid' => $payments->whereIn('status', ['paid', 'captured'])->count(),
            'pending' => $payments->whereNotIn('status', ['paid', 'captured', 'failed'])->count(),
            'online_amount' => $payments->where('payment_method', 'razorpay')->whereIn('status', ['paid', 'captured'])->sum('amount'),
            'cash_amount' => $payments->where('payment_method', 'cash')->whereIn('status', ['paid', 'captured'])->sum('amount'),
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

    public function updatedFilterMethod()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
};
?>

<section class="space-y-8">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Payments') }}</flux:heading>
                <flux:subheading size="lg" class="mb-6">{{ __('Review membership collections, payment methods, and Razorpay references in one place') }}</flux:subheading>
            </div>
        </div>

        <flux:separator variant="subtle" />
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('All Payments') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->paymentStats['total'] }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Verified Paid') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->paymentStats['paid'] }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Pending') }}</flux:text>
            <flux:heading size="lg" class="mt-2">{{ $this->paymentStats['pending'] }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Online Collected') }}</flux:text>
            <flux:heading size="lg" class="mt-2">INR {{ number_format((float) $this->paymentStats['online_amount'], 2) }}</flux:heading>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500">{{ __('Cash Collected') }}</flux:text>
            <flux:heading size="lg" class="mt-2">INR {{ number_format((float) $this->paymentStats['cash_amount'], 2) }}</flux:heading>
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div>
            <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Filter by library, status, method, or search by student and payment reference.') }}</flux:text>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <flux:select wire:model.live="filter_library_id" label="Library">
                <flux:select.option value="">{{ __('All Libraries') }}</flux:select.option>
                @foreach ($this->libraries as $library)
                    <flux:select.option value="{{ $library->id }}">{{ $library->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter_status" label="Status">
                <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                <flux:select.option value="captured">{{ __('Captured') }}</flux:select.option>
                <flux:select.option value="created">{{ __('Created') }}</flux:select.option>
                <flux:select.option value="authorized">{{ __('Authorized') }}</flux:select.option>
                <flux:select.option value="failed">{{ __('Failed') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="filter_method" label="Method">
                <flux:select.option value="">{{ __('All Methods') }}</flux:select.option>
                <flux:select.option value="cash">{{ __('Cash') }}</flux:select.option>
                <flux:select.option value="razorpay">{{ __('Razorpay') }}</flux:select.option>
            </flux:select>

            <flux:input wire:model.live.debounce.300ms="search" label="Search" placeholder="Student, library, order id, reference..." />
        </div>
    </div>

    <div class="space-y-4">
        <flux:table :paginate="$this->payments">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'student'" :direction="$sortDirection" wire:click="sort('student')">
                    {{ __('Student') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'library'" :direction="$sortDirection" wire:click="sort('library')">
                    {{ __('Library') }}
                </flux:table.column>
                <flux:table.column>{{ __('Membership') }}</flux:table.column>
                <flux:table.column>{{ __('Method') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                    {{ __('Date') }}
                </flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->payments as $payment)
                    @php
                        $statusColor = in_array($payment->status, ['paid', 'captured'], true)
                            ? 'green'
                            : (in_array($payment->status, ['failed'], true) ? 'red' : 'amber');
                    @endphp
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="font-semibold">{{ $payment->user?->name ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $payment->user?->email ?? '-' }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="font-medium">{{ $payment->library?->name ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $payment->library?->city ?? __('City not set') }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="font-medium">{{ __('Seat') }} {{ $payment->membership?->seat?->seat_number ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $payment->membership?->seat?->room?->name ?? __('Room not linked') }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-medium">{{ ucfirst($payment->payment_method ?? 'pending') }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :color="$statusColor">{{ ucfirst($payment->status) }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-semibold">INR {{ number_format((float) $payment->amount, 2) }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1 text-sm">
                                <div>{{ $payment->razorpay_payment_id ?? $payment->reference ?? '-' }}</div>
                                @if ($payment->razorpay_order_id)
                                    <div class="text-xs text-zinc-500">{{ $payment->razorpay_order_id }}</div>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ ($payment->paid_at ?? $payment->created_at)?->format('d M Y h:i A') ?? '-' }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">
                            <flux:text>{{ __('No payments found for the current filters.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
