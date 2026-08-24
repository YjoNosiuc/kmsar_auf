<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserApprovedMail;
use App\Models\College;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    private const KMSAR_ROLES = [
        'super_admin',
        'ovpri_admin',
        'cdaic_admin',
        'college_dean',
        'faculty',
        'viewer',
    ];

    /**
     * Human labels for roles assignable through user management.
     *
     * @return array<string, string>
     */
    public static function kmsarRoleLabels(): array
    {
        return [
            'super_admin' => __('Super Admin'),
            'ovpri_admin' => __('OVPRI Admin'),
            'cdaic_admin' => __('CDAIC Admin'),
            'college_dean' => __('Dean/Head'),
            'faculty' => __('Faculty'),
            'viewer' => __('Viewer'),
        ];
    }

    public function index(): View
    {
        $users = User::query()
            ->where('is_pending', false)
            ->with(['roles', 'college', 'program'])
            ->orderBy('name')
            ->get();

        $pendingUsers = User::query()
            ->where('is_pending', true)
            ->with(['college', 'program', 'roles'])
            ->orderByDesc('created_at')
            ->get();

        $colleges = College::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('admin.users.index', [
            'users' => $users,
            'pendingUsers' => $pendingUsers,
            'colleges' => $colleges,
            'kmsarRoles' => self::kmsarRoleLabels(),
        ]);
    }

    public function create()
    {
        return response('users.create');
    }

    public function store(Request $request)
    {
        $this->normalizeOptionalUserFields($request);

        $validated = $request->validate([
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                'unique:users,employee_number',
                Rule::requiredIf(fn () => $request->input('user_type') !== 'external_affiliate'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'office' => ['nullable', 'string', 'max:100'],
            'user_type' => ['nullable', 'in:faculty,staff,student,external_affiliate'],
            'institution' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(self::KMSAR_ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'employee_number' => $validated['employee_number'] ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_name' => $validated['middle_name'] ?: null,
            'suffix' => filled($validated['suffix'] ?? '') ? trim((string) $validated['suffix']) : null,
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'college_id' => $validated['college_id'] ?? null,
            'program_id' => $validated['program_id'] ?? null,
            'office' => filled($validated['office'] ?? '') ? strtoupper(trim((string) $validated['office'])) : null,
            'user_type' => $validated['user_type'] ?? null,
            'institution' => $validated['institution'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_pending' => false,
        ]);

        $user->syncRoles([$validated['role']]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('User created successfully.'));
    }

    public function show(User $user)
    {
        return response('users.show');
    }

    public function edit(User $user): JsonResponse
    {
        return response()->json([
            'id' => $user->id,
            'employee_number' => $user->employee_number,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'suffix' => $user->suffix,
            'email' => $user->email,
            'college_id' => $user->college_id,
            'program_id' => $user->program_id,
            'office' => $user->office,
            'user_type' => $user->user_type,
            'institution' => $user->institution,
            'role' => $user->getRoleNames()->first(),
            'is_active' => $user->is_active,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->normalizeOptionalUserFields($request);

        $validated = $request->validate([
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'employee_number')->ignore($user->id),
                Rule::requiredIf(fn () => $request->input('user_type') !== 'external_affiliate'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'office' => ['nullable', 'string', 'max:100'],
            'user_type' => ['nullable', 'in:faculty,staff,student,external_affiliate'],
            'institution' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(self::KMSAR_ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->fill([
            'employee_number' => $validated['employee_number'] ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_name' => $validated['middle_name'] ?: null,
            'suffix' => filled($validated['suffix'] ?? '') ? trim((string) $validated['suffix']) : null,
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'college_id' => $validated['college_id'] ?? null,
            'program_id' => $validated['program_id'] ?? null,
            'office' => filled($validated['office'] ?? '') ? strtoupper(trim((string) $validated['office'])) : null,
            'user_type' => $validated['user_type'] ?? null,
            'institution' => $validated['institution'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('User updated successfully.'));
    }

    public function destroy(User $user)
    {
        return response('users.destroy');
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->is_pending, 404);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:faculty,viewer,college_dean,ovpri_admin,cdaic_admin,super_admin'],
        ]);

        $user->update(['is_active' => true, 'is_pending' => false]);
        $user->syncRoles([$validated['role']]);

        try {
            Mail::to($user->email)->send(new UserApprovedMail($user));
        } catch (\Exception $e) {
            Log::warning('Approval email failed: '.$e->getMessage());
        }

        return redirect()->route('admin.users.index')
            ->with('success', __('User approved and activated successfully.'));
    }

    public function reject(User $user): RedirectResponse
    {
        abort_unless($user->is_pending, 404);

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', __('Registration rejected and removed.'));
    }

    private function normalizeOptionalUserFields(Request $request): void
    {
        $request->merge([
            'employee_number' => filled($request->input('employee_number'))
                ? $request->input('employee_number')
                : null,
            'institution' => filled($request->input('institution'))
                ? trim((string) $request->input('institution'))
                : null,
            'user_type' => filled($request->input('user_type'))
                ? $request->input('user_type')
                : null,
        ]);
    }
}
