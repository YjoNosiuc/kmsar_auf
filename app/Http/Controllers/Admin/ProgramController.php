<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    public function edit(Program $program): JsonResponse
    {
        return response()->json([
            'id' => $program->id,
            'name' => $program->name,
            'code' => $program->code,
            'college_id' => $program->college_id,
            'is_active' => $program->is_active,
        ]);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:30', Rule::unique('programs', 'code')->ignore($program->id)],
            'college_id' => ['required', 'exists:colleges,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $program->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'college_id' => $validated['college_id'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.colleges.index')
            ->with('success', __('Program updated successfully.'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'college_id' => ['required', 'exists:colleges,id'],
            'code' => ['required', 'string', 'max:30', 'unique:programs,code'],
            'name' => ['required', 'string', 'max:200'],
        ]);

        Program::create([
            'college_id' => $validated['college_id'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return redirect()->route('admin.colleges.index')
            ->with('success', __('Program added successfully.'));
    }

    public function destroy(Program $program): RedirectResponse
    {
        $program->delete();

        return redirect()->route('admin.colleges.index')
            ->with('success', __('Program deleted successfully.'));
    }
}
