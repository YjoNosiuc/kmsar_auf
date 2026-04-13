<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $colleges = \App\Models\College::orderBy('name')->get();

        return view('profile.edit', compact('user', 'colleges'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'profile')
                ->withInput();
        }

        $validated = $validator->validated();

        $user->update([
            'first_name' => strtoupper($validated['first_name']),
            'last_name' => strtoupper($validated['last_name']),
            'middle_name' => $validated['middle_name']
                ? strtoupper($validated['middle_name'])
                : null,
            'suffix' => $validated['suffix'] ?? null,
            'name' => strtoupper($validated['first_name'])
                .' '.strtoupper($validated['last_name']),
            'email' => strtolower($validated['email']),
        ]);

        return back()->with('success',
            'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
            ],
        ], [
            'password.different' => 'New password must be different from your current password.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'password')
                ->withInput();
        }

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors([
                    'current_password' => 'Current password is incorrect.',
                ], 'password')
                ->withInput();
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success',
            'Password changed successfully.');
    }
}
