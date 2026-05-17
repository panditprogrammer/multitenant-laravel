<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[260px]">
        <flux:navlist aria-label="{{ __('Setup & Configurations') }}">
            <flux:navlist.item :href="route('setup.payment-gateway.edit')" wire:navigate>
                {{ __('Payment Gateway Settings') }}
            </flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-3xl">
            {{ $slot }}
        </div>
    </div>
</div>
