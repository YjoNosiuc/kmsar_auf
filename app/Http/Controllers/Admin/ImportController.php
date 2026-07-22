<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\ResearchImport;
use App\Imports\UserImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function showUserImport(): View
    {
        return view('admin.import.users');
    }

    public function importUsers(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $import = new UserImport;
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('admin.import.users')
            ->with('import_results', $import->results());
    }

    public function showResearchImport(): View
    {
        return view('admin.import.research');
    }

    public function importResearch(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $import = new ResearchImport;
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('admin.import.research')
            ->with('import_results', $import->results());
    }
}
