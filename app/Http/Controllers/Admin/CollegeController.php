<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollegeController extends Controller
{
    public function index(): View
    {
        $colleges = College::query()
            ->withCount('programs')
            ->orderBy('name')
            ->get();

        $programs = Program::query()
            ->with('college')
            ->orderBy('college_id')
            ->orderBy('code')
            ->get();

        return view('admin.colleges.index', [
            'colleges' => $colleges,
            'programs' => $programs,
        ]);
    }

    public function create()
    {
        return response('colleges.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:colleges,code'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        College::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        return redirect()->route('admin.colleges.index')
            ->with('success', 'College added successfully.');
    }

    public function show(College $college)
    {
        return response('colleges.show');
    }

    public function edit(College $college): JsonResponse
    {
        return response()->json([
            'id' => $college->id,
            'name' => $college->name,
            'code' => $college->code,
            'is_active' => $college->is_active,
        ]);
    }

    public function update(Request $request, College $college): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:10', Rule::unique('colleges', 'code')->ignore($college->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $college->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.colleges.index')
            ->with('success', __('College updated successfully.'));
    }

    public function toggleActive(College $college): RedirectResponse
    {
        $college->update(['is_active' => ! $college->is_active]);

        return redirect()
            ->route('admin.colleges.index')
            ->with('success', __('College status updated.'));
    }

    public function destroy(College $college)
    {
        if ($college->programs()->count() > 0) {
            return redirect()->route('admin.colleges.index')
                ->with('error', 'Cannot delete college with existing programs. Remove all programs first.');
        }

        $college->delete();

        return redirect()->route('admin.colleges.index')
            ->with('success', 'College deleted successfully.');
    }
}
