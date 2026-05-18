<?php

namespace App\Livewire\Settings;

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('General settings')]
class General extends Component
{
    public string $razorpay_key_id = '';

    public string $razorpay_key_secret = '';

    public string $razorpay_webhook_secret = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'owner' && is_null(Auth::user()?->owner_id), 403);

        $user = Auth::user();

        $this->razorpay_key_id = $user->razorpay_key_id ?? '';
        $this->razorpay_key_secret = $user->razorpay_key_secret ?? '';
        $this->razorpay_webhook_secret = $user->razorpay_webhook_secret ?? '';
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->role === 'owner' && is_null(Auth::user()?->owner_id), 403);

        $validated = $this->validate([
            'razorpay_key_id' => ['required', 'string', 'max:255'],
            'razorpay_key_secret' => ['required', 'string', 'max:255'],
            'razorpay_webhook_secret' => ['required', 'string', 'max:255'],
        ]);

        Auth::user()->update($validated);

        Flux::toast(variant: 'success', text: __('General settings updated.'));
    }

    #[Computed]
    public function webhookUrl(): string
    {
        return route('payments.webhooks.razorpay');
    }

    #[Computed]
    public function paymentGatewayReady(): bool
    {
        return filled($this->razorpay_key_id)
            && filled($this->razorpay_key_secret)
            && filled($this->razorpay_webhook_secret);
    }
}
