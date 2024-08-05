<x-tenant-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                
                <div class="p-6 py-4 border-b text-gray-900 flex justify-between items-center">
                    <h1 class="text-2xl font-bold my-4">{{ __("You're User!") }}</h1>
                    <x-a-btn href="{{ route('app.users.index') }}"> View Users</x-a-btn>
                </div>
            </div>
        </div>
    </div>
</x-tenant-app-layout>
