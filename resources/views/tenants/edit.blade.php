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
                    Create edit form and <br>
                    Edit your Tenant here...
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
