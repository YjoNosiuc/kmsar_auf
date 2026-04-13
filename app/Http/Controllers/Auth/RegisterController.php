<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect()->route('research.index');
        }
        $colleges = College::orderBy('name')->get();

        return view('auth.register', compact('colleges'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'employee_number' => ['required', 'string', 'max:50', 'unique:users,employee_number'],
            'college_id' => ['required', 'exists:colleges,id'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'first_name' => strtoupper($validated['first_name']),
            'last_name' => strtoupper($validated['last_name']),
            'middle_name' => $validated['middle_name']
                ? strtoupper($validated['middle_name'])
                : null,
            'suffix' => $validated['suffix'] ?? null,
            'name' => strtoupper($validated['first_name'])
                .' '.strtoupper($validated['last_name']),
            'employee_number' => strtoupper($validated['employee_number']),
            'college_id' => $validated['college_id'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        $user->assignRole('faculty');

        Auth::login($user);

        return redirect()->route('research.index')
            ->with('success', 'Welcome to KMSAR! Your account has been created.');
    }
}
