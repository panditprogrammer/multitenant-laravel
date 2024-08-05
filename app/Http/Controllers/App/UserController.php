<?php

namespace App\Http\Controllers\App;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['users'] = User::latest()->get();
        return view("app.users.index", $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view("app.users.create");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string",
            "email" => "required|email|unique:" . User::class,
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $validated['password'] = Hash::make($request->password);

        $user = User::create($validated);
        if ($user) {
            return back()->with("success", "User Created Successfully");
        }
        return back()->with("error", "Failed to created user!");
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        return view("app.users.edit", ["user" => $user]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Remove the password key if it is empty
        if (empty($request->input('password'))) {
            $validated = $request->except('password');
        } else {
            $validated['password'] = Hash::make($request->password);
        }


        if ($user->update($validated)) {
            return back()->with("success", "User Updated Successfully");
        } else {
            return back()->with("error", "Failed to update user!");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {

        try {
            if ($user->delete()) {
                return back()->with("success", "User Deleted Successfully");
            } else {
                return back()->with("error", "Failed to delete user!");
            }
        } catch (\Throwable $th) {
            return back()->with("error", $th);
        }
    }
}
