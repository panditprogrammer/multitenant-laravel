<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class TenantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['tenants'] = Tenant::with("domains")->latest()->get();
        return view("tenants.index", $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view("tenants.create");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string",
            "domainName" => "required|string|unique:domains,domain",
            "email" => "required|email|unique:" . Tenant::class,
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $tenant = Tenant::create($validated);
        if ($tenant) {
            $tenant->domains()->create([
                "domain" => $validated['domainName'] . "." . config("app.domain")

            ]);
            return back()->with("success", "Tenant Created Successfully");
        }
        return back()->with("error", "Failed to created tenant!");
    }

    /**
     * Display the specified resource.
     */
    public function show(Tenant $tenant)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tenant $tenant)
    {
        return view("tenants.edit", ['tenant' => $tenant]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:tenants,email,' . $tenant->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Remove the password key if it is empty
        if (empty($request->input('password'))) {
            $validated = $request->except('password');
        }

        if ($tenant->update($validated)) {

            // update admin user for this tenant
            $tenant->run(function () use ($tenant) {
                $user = User::where("email", $tenant->email)->first();

                if ($user) {
                    $user->name = $tenant->name;
                    $user->email = $tenant->email;
                    $user->password = $tenant->password;
                }
                $user->save();
            });

            return back()->with("success", "Tenant Updated Successfully");
        } else {
            return back()->with("error", "Failed to update tenant!");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tenant $tenant)
    {
        if ($tenant->delete()) {
            return back()->with("success", "Tenant Deleted Successfully");
        } else {
            return back()->with("error", "Failed to delete tenant!");
        }
    }
}
