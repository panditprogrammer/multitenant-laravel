<x-tenant-app-layout>
    <x-slot name="header justify-space-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Users') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 py-4 border-b text-gray-900 flex justify-between items-center">
                    <h1 class="text-2xl font-bold my-4">Users List</h1>
                    <x-a-btn href="{{ route('app.users.create') }}"> Create User</x-a-btn>
                </div>

                <div class="p-6 text-gray-900">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b">Name</th>
                                <th class="py-2 px-4 border-b">Email</th>
                                <th class="py-2 px-4 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr class="text-center">
                                    <td class="py-2 px-4 border-b">{{ $user->name }}</td>
                                    <td class="py-2 px-4 border-b">{{ $user->email }}</td>

                                    <td class="py-2 px-4 border-b">
                                        <a href="{{ route('app.users.edit', $user->id) }}"
                                            class="p-1 px-2 text-green-700">
                                            <i class="fa fa-edit" aria-hidden="true"></i>
                                        </a>
                                        <form class="inline-block p-1 px-2 text-red-700"
                                            action="{{ route('app.users.destroy', $user->id) }}" method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"><i class="fa fa-trash text-danger"
                                                    aria-hidden="true"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-tenant-app-layout>
