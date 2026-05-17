<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Setup & Configurations') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage payment APIs and other system-facing configuration for your owner account') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <flux:heading class="sr-only">{{ __('Setup & Configurations') }}</flux:heading>

    <x-setup.layout :heading="__('Payment Gateway Settings')" :subheading="__('Save your Razorpay keys, webhook secret, and the webhook URL you must configure in the Razorpay dashboard')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <div class="space-y-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div>
                    <flux:heading size="lg">{{ __('Payment API') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Add your Razorpay credentials here. Students paying under your libraries will use these keys only.') }}</flux:text>
                </div>

                <flux:input wire:model="razorpay_key_id" :label="__('Razorpay Key Id')" type="text" required />
                <flux:input wire:model="razorpay_key_secret" :label="__('Razorpay Key Secret')" type="password" required viewable />
                <flux:input wire:model="razorpay_webhook_secret" :label="__('Razorpay Webhook Secret')" type="password" required viewable />
            </div>

            <div class="space-y-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div>
                    <flux:heading size="lg">{{ __('System Details') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Use this webhook URL in your Razorpay dashboard. Multiple owners can use the same URL because each payment is verified against the saved secret for that owner.') }}</flux:text>
                </div>

                <flux:input :label="__('Webhook Url')" :value="$this->webhookUrl" type="text" readonly copyable />
                <flux:input :label="__('Recommended Events')" :value="'payment.captured, order.paid, payment.failed'" type="text" readonly />

                <div class="rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-900">
                    <div class="font-medium text-zinc-800 dark:text-zinc-100">
                        {{ $this->paymentGatewayReady ? __('Payment gateway is configured.') : __('Payment gateway is incomplete.') }}
                    </div>
                    <div class="mt-1 text-zinc-500">
                        {{ $this->paymentGatewayReady
                            ? __('Students can pay online once this same webhook URL is added in your Razorpay dashboard.')
                            : __('Save all three Razorpay values to enable owner-specific online payments and webhook verification.') }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </x-setup.layout>
</section>
