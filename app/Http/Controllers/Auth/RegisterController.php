<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('research.index');
        }
        $colleges = College::orderBy('name')->get();

        return view('auth.register', compact('colleges'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'employee_number' => filled($request->input('employee_number'))
                ? $request->input('employee_number')
                : null,
            'institution' => filled($request->input('institution'))
                ? trim((string) $request->input('institution'))
                : null,
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                'unique:users,employee_number',
                Rule::requiredIf(fn () => in_array($request->input('user_type'), ['faculty', 'staff', 'student'], true)),
            ],
            'college_id' => ['required', 'exists:colleges,id'],
            'program_id' => [
                'nullable',
                'exists:programs,id',
            ],
            'user_type' => ['required', 'in:faculty,staff,student,external_affiliate'],
            'institution' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'suffix' => $validated['suffix'] ?? null,
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'employee_number' => $validated['employee_number'] ?? null,
            'college_id' => $validated['college_id'],
            'program_id' => $validated['program_id'] ?? null,
            'user_type' => $validated['user_type'],
            'institution' => $validated['institution'] ?? null,
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'is_active' => false,
            'is_pending' => true,
        ]);

        $user->assignRole('viewer');

        return redirect()->route('login')
            ->with('info', __('Your account has been submitted for approval. You will be notified once approved by the administrator.'));
    }
}
