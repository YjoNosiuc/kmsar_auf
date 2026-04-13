<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    private const KMSAR_ROLES = [
        'super_admin',
        'ovpri_admin',
        'cdaic_admin',
        'college_dean',
        'unit_head',
        'faculty',
        'co_author',
        'registrar',
        'viewer',
    ];

    /**
     * Human labels for the nine KMSAR roles (RolePermissionSeeder).
     *
     * @return array<string, string>
     */
    public static function kmsarRoleLabels(): array
    {
        return [
            'super_admin' => __('Super Admin'),
            'ovpri_admin' => __('OVPRI Admin'),
            'cdaic_admin' => __('CDAIC Admin'),
            'college_dean' => __('College Dean'),
            'unit_head' => __('Unit Head'),
            'faculty' => __('Faculty'),
            'co_author' => __('Co-Author'),
            'registrar' => __('Registrar'),
            'viewer' => __('Viewer'),
        ];
    }

    public function index(): View
    {
        $users = User::query()
            ->with(['roles', 'college'])
            ->orderBy('name')
            ->get();

        $colleges = College::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('admin.users.index', [
            'users' => $users,
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
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:20', 'unique:users,employee_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'role' => ['required', 'string', Rule::in(self::KMSAR_ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'employee_number' => $validated['employee_number'],
            'first_name' => strtoupper($validated['first_name']),
            'last_name' => strtoupper($validated['last_name']),
            'middle_name' => $validated['middle_name']
                ? strtoupper($validated['middle_name'])
                : null,
            'suffix' => filled($validated['suffix'] ?? '') ? trim((string) $validated['suffix']) : null,
            'name' => strtoupper($validated['first_name']).' '.strtoupper($validated['last_name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'college_id' => $validated['college_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
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
            'role' => $user->getRoleNames()->first(),
            'is_active' => $user->is_active,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'employee_number' => ['required', 'string', 'max:20', Rule::unique('users', 'employee_number')->ignore($user->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'role' => ['required', 'string', Rule::in(self::KMSAR_ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->fill([
            'employee_number' => $validated['employee_number'],
            'first_name' => strtoupper($validated['first_name']),
            'last_name' => strtoupper($validated['last_name']),
            'middle_name' => $validated['middle_name']
                ? strtoupper($validated['middle_name'])
                : null,
            'suffix' => filled($validated['suffix'] ?? '') ? trim((string) $validated['suffix']) : null,
            'name' => strtoupper($validated['first_name']).' '.strtoupper($validated['last_name']),
            'email' => $validated['email'],
            'college_id' => $validated['college_id'] ?? null,
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
}
