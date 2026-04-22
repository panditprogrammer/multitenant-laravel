<?php

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $name, $email, $password;
    public $libstudents = [];
    public $editId = null;
    public $library_id;
    public $libraries = [];

    public function mount()
    {
        $this->loadStudents();
        $this->libraries = auth()->user()->libraries;
    }

    public function loadStudents()
    {
        $this->libstudents = Auth::user()->libraries()->with('students')->latest()->get();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required',
            'email' => 'required|email',
            // 'phone' => 'required',
            'library_id' => 'required|exists:libraries,id',
        ]);

        if ($this->editId) {
            $student = User::find($this->editId);
        } else {
            $student = new User();
            $student->password = Hash::make($this->password ?? '123456');
        }

        $student->name = $this->name;
        $student->email = $this->email;
        // $student->phone = $this->phone;
        $student->role = 'student';
        $student->library_id = $this->library_id;
        $student->save();

        $this->resetForm();
        $this->loadStudents();
    }

    public function edit($id)
    {
        $student = User::find($id);

        $this->editId = $id;
        $this->name = $student->name;
        $this->email = $student->email;
        // $this->phone = $student->phone;
        $this->library_id = $student->library_id;
    }

    public function delete($id)
    {
        User::find($id)?->delete();
        $this->loadStudents();
    }

    public function resetForm()
    {
        $this->reset(['name', 'email', 'password', 'editId', 'library_id']);
    }
};
?>
<section class="space-y-6">

    <!-- 🎓 HEADING -->
    <flux:heading size="lg">Student Management</flux:heading>

    <!-- 🧑‍🎓 STUDENT FORM -->
    <form wire:submit="save" class="grid md:grid-cols-4 gap-3">

        <flux:select wire:model="library_id" label="Library" required>
            <flux:select.option value="">Select Library</flux:select.option>

            @foreach ($this->libraries as $lib)
                <flux:select.option value="{{ $lib->id }}">
                    {{ $lib->name }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="name" label="Student Name" required />

        <flux:input wire:model="email" label="Email" type="email" required />

        {{-- <flux:input wire:model="phone" label="Phone" required /> --}}

        @if (!$editId)
            <flux:input wire:model="password" label="Password" type="password" />
        @endif

        <flux:button type="submit" variant="primary" class="mt-6">
            {{ $editId ? 'Update' : 'Create' }}
        </flux:button>

    </form>

    <!-- 📊 STUDENT TABLE -->
    <flux:table>

        <flux:table.columns>
            <flux:table.column>Student</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            {{-- <flux:table.column>Phone</flux:table.column> --}}
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>

            @forelse ($this->libstudents as $library)
                @forelse ($library->students as $student)
                    <flux:table.row :key="$student->id">

                        <!-- Student -->
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:avatar size="xs" src="{{ $student->profile_image_url }}" />
                            {{ $student->name }}
                        </flux:table.cell>

                        <!-- Email -->
                        <flux:table.cell>
                            {{ $student->email }}
                        </flux:table.cell>

                        {{-- <!-- Phone -->
                    <flux:table.cell>
                        {{ $student->phone }}
                    </flux:table.cell> --}}

                        <!-- Actions -->
                        <flux:table.cell align="end">

                            <div class="flex gap-2 justify-end">

                                <flux:button size="sm" variant="ghost" wire:click="edit({{ $student->id }})">
                                    Edit
                                </flux:button>

                                <flux:button size="sm" variant="danger"
                                    wire:confirm="Are you sure you want to delete this student?"
                                    wire:click="delete({{ $student->id }})">
                                    Delete
                                </flux:button>

                            </div>

                        </flux:table.cell>

                    </flux:table.row>

                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text>No students found.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse

            @empty
            @endforelse

        </flux:table.rows>

    </flux:table>

</section>
