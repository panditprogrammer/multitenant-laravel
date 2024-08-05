<x-app-layout>
    <x-slot name="header justify-space-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Add Tenant') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 py-4 border-b text-gray-900 flex justify-between items-center">
                    <h1 class="text-2xl font-bold my-4">Edit Tenant</h1>
                    <x-a-btn href="{{ route('tenants.index') }}"> View Tenants</x-a-btn>
                </div>

                <div class="max-w-xl p-6 text-gray-900">
                    <form method="POST" action="{{ route('tenants.update',$tenant) }}">
                        @csrf
                        @method("PATCH")
                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__(' Name')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                                :value="old('name',$tenant->name)" required autofocus autocomplete="name" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>


                       
                        <!-- Email Address -->
                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                                :value="old('email',$tenant->email)" required autocomplete="username" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <!-- Password -->
                        <div class="mt-4">
                            <x-input-label for="password" :value="__('Password')" />

                            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password"
                                autocomplete="new-password" />
                                <p class="text-gray-500 my-2 text-sm"><strong>NOTE: </strong> Leave blank to keep old password.</p>

                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

                            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password"
                                name="password_confirmation" autocomplete="new-password" />

                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">

                            <x-primary-button class="ms-4">
                                {{ __('Update Tenant') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
