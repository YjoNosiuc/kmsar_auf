<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Program;
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

        $programCollegeId = old('college_id', $user->college_id);

        $programs = $programCollegeId
            ? Program::query()
                ->where('college_id', $programCollegeId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
            : collect();

        $savedProgramId = old('program_id', $user->program_id);
        if ($savedProgramId && $programCollegeId && ! $programs->contains('id', (int) $savedProgramId)) {
            $savedProgram = Program::query()->find($savedProgramId);
            if ($savedProgram !== null && (int) $savedProgram->college_id === (int) $programCollegeId) {
                $programs->push($savedProgram);
                $programs = $programs->sortBy('code')->values();
            }
        }

        $programsForSelect = $programs->map(fn (Program $program) => [
            'id' => $program->id,
            'code' => $program->code,
            'name' => $program->name,
        ])->values();

        $profileCollegeProgramInitial = [
            'selectedCollegeId' => old('college_id', $user->college_id)
                ? (string) old('college_id', $user->college_id)
                : '',
            'selectedProgramId' => old('program_id', $user->program_id)
                ? (string) old('program_id', $user->program_id)
                : '',
            'programs' => $programsForSelect,
            'programsUrl' => route('api.programs'),
        ];

        $nameFieldsLocked = $this->locksProfileNameFields($user);

        return view('profile.edit', compact(
            'user',
            'colleges',
            'programs',
            'programsForSelect',
            'profileCollegeProgramInitial',
            'nameFieldsLocked',
        ));
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $nameFieldsLocked = $this->locksProfileNameFields($user);

        $currentEmployeeNumber = $user->employee_number;

        $rules = [
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
        ];

        if (! $nameFieldsLocked) {
            $rules['first_name'] = ['required', 'string', 'max:100'];
            $rules['last_name'] = ['required', 'string', 'max:100'];
        }

        $validator = Validator::make($request->all(), $rules + [
            'college_id' => ['nullable', 'exists:colleges,id'],
            'program_id' => [
                'nullable',
                Rule::exists('programs', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $collegeId = $request->input('college_id');
                    if (! $collegeId) {
                        return;
                    }
                    $belongsToCollege = Program::query()
                        ->whereKey($value)
                        ->where('college_id', $collegeId)
                        ->exists();
                    if (! $belongsToCollege) {
                        $fail(__('The selected program does not belong to the selected college.'));
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, 'profile')
                ->withInput();
        }

        $validated = $validator->validated();

        $firstName = $nameFieldsLocked ? $user->first_name : $validated['first_name'];
        $lastName = $nameFieldsLocked ? $user->last_name : $validated['last_name'];

        $user->update([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => $validated['middle_name'] ?: null,
            'suffix' => $validated['suffix'] ?? null,
            'name' => trim($firstName.' '.$lastName),
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

    private function locksProfileNameFields(\App\Models\User $user): bool
    {
        return ! $user->hasAnyRole(['ovpri_admin', 'cdaic_admin', 'super_admin']);
    }
}
