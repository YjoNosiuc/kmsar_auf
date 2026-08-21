<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'exclude' => ['nullable', 'array'],
            'exclude.*' => ['integer'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $excludeIds = array_values(array_unique($validated['exclude'] ?? []));

        $users = User::query()
            ->with(['college', 'program', 'roles'])
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';

                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('employee_number', 'like', $term);
                });
            })
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_number' => $user->employee_number,
                'college' => $user->college?->name ?? '—',
                'college_code' => $user->college?->code ?? '—',
                'program' => $user->program?->name ?? $user->office ?? '—',
                'role' => $user->getRoleNames()->first() ?? '—',
                'user_type' => $user->user_type ?? '—',
            ])
            ->values();

        return response()->json($users);
    }
}
