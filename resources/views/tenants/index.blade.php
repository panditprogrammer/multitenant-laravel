<x-app-layout>
    <x-slot name="header justify-space-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tenants') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 py-4 border-b text-gray-900 flex justify-between items-center">
                    <h1 class="text-2xl font-bold my-4">Tenants List</h1>
                    <x-a-btn href="{{ route('tenants.create') }}"> Create Tenant</x-a-btn>
                </div>

                <div class="p-6 text-gray-900">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b">Name</th>
                                <th class="py-2 px-4 border-b">Email</th>
                                <th class="py-2 px-4 border-b">Domains</th>
                                <th class="py-2 px-4 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tenants as $tenant)
                                <tr class="text-center">
                                    <td class="py-2 px-4 border-b">{{ $tenant->name }}</td>
                                    <td class="py-2 px-4 border-b">{{ $tenant->email }}</td>
                                    <td class="py-2 px-4 border-b">
                                        @forelse ($tenant->domains as $domain)
                                            <a class="p-1 hover:text-blue-700" target="_blank"
                                                href="http://{{ $domain->domain }}:8000">{{ $domain->domain }}</a>
                                        @empty
                                        @endforelse
                                    </td>
                                    <td class="py-2 px-4 border-b">
                                        <a href="{{ route('tenants.edit', $tenant->id) }}"
                                            class="p-1 px-2 text-green-700">
                                            <i class="fa fa-edit" aria-hidden="true"></i>
                                        </a>
                                        <a onclick="return confirm('Are you sure to delete?')"
                                            href="{{ route('tenants.destroy', $tenant->id) }}"
                                            class="p-1 px-2 text-red-700">
                                            <i class="fa fa-trash text-danger" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
