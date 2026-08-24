<?php

namespace App\Http\Controllers;

use App\Models\College;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = auth()->user()->loadMissing(['college', 'program', 'roles']);
        $colleges = College::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('profile.edit', compact('user', 'colleges'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $currentEmployeeNumber = $user->employee_number;

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'employee_number' => [
                'nullable',
                'string',
                Rule::unique('users', 'employee_number')->ignore($user->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($currentEmployeeNumber): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if ((string) $value === (string) $currentEmployeeNumber) {
                        return;
                    }
                    if (! preg_match('/^[0-9]{1,10}$/', (string) $value)) {
                        $fail(__('The ID number must be 1 to 10 digits.'));
                    }
                },
            ],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'profile')
                ->withInput();
        }

        $validated = $validator->validated();

        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_name' => $validated['middle_name'] ?: null,
            'suffix' => $validated['suffix'] ?? null,
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'employee_number' => $validated['employee_number'] ?? null,
            'college_id' => $validated['college_id'] ?? null,
            'program_id' => $validated['program_id'] ?? null,
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
