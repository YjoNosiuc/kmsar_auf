<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CollegeController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\UserController;
use App\Models\College;
use App\Models\Research;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalFileController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DeanController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OvpriController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\UserSearchController;

// To process queued emails run: php artisan queue:work --sleep=3 --tries=3

Route::get('/', function () {
if (auth()->check()) {
$role = auth()->user()->getRoleNames()->first();
return match($role) {
'super_admin' => redirect()->route('admin.dashboard'),
'ovpri_admin', 'cdaic_admin' => redirect()->route('ovpri.dashboard'),
'college_dean', 'unit_head' => redirect()->route('dean.dashboard'),
'faculty', 'viewer' => redirect()->route('research.index'),
default => redirect()->route('login'),
};
}
return redirect()->route('login');
});
/*
|--------------------------------------------------------------------------
| Auth (guest) — intentionally WITHOUT nocache middleware.
| no-store on the login page causes browsers to drop the CSRF token → 419 Page Expired.
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])
        ->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp'])->name('password.email');
    Route::get('/verify-otp', [PasswordResetController::class, 'showVerifyForm'])->name('password.verify');
    Route::post('/verify-otp', [PasswordResetController::class, 'verifyOtp'])->name('password.verify.submit');
    Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});
Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'nocache'])->group(function () {
    Route::get('/api/users/search', [UserSearchController::class, 'search'])
        ->name('api.users.search');

    Route::get('/profile',
        [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile',
        [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::patch('/profile/password',
        [ProfileController::class, 'updatePassword'])
        ->name('profile.password');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
});

/*
|--------------------------------------------------------------------------
| Faculty & viewer — research module
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'nocache', 'role:faculty|viewer|super_admin'])
    ->prefix('research')
    ->group(function () {
        Route::get('/', [ResearchController::class, 'index'])->name('research.index');
        Route::get('/create', [ResearchController::class, 'create'])->name('research.create');
        Route::post('/', [ResearchController::class, 'store'])->name('research.store');
        Route::get('/{research}/details', [ResearchController::class, 'registrationDetails'])->name('research.wizard.details');
        Route::put('/{research}/details', [ResearchController::class, 'saveRegistrationDetails'])->name('research.wizard.details.save');
        Route::get('/{research}/authors', [ResearchController::class, 'registrationAuthors'])->name('research.wizard.authors');
        Route::post('/{research}/authors', [ResearchController::class, 'saveRegistrationAuthors'])->name('research.wizard.authors.save');
        Route::get('/{research}/documents', [ResearchController::class, 'registrationDocuments'])->name('research.wizard.documents');
        Route::put('/{research}/update-progress', [ResearchController::class, 'updateProgress'])->name('research.update-progress');
        Route::get('/{research}', [ResearchController::class, 'show'])->name('research.show');
        Route::get('/{research}/edit', [ResearchController::class, 'edit'])->name('research.edit');
        Route::put('/{research}', [ResearchController::class, 'update'])->name('research.update');
        Route::delete('/{research}', [ResearchController::class, 'destroy'])->name('research.destroy');
        Route::post('/{research}/submit', [ResearchController::class, 'submit'])->name('research.submit');
        Route::post('/{research}/revise', [ResearchController::class, 'revise'])->name('research.revise');
        Route::post('/{research}/documents', [DocumentController::class, 'store'])->name('documents.upload');
        Route::get('/{research}/documents/{document}/download', [FileController::class, 'download'])->name('documents.download');
        Route::get('/{research}/documents/{document}/preview', [FileController::class, 'preview'])->name('documents.preview');
    });

Route::middleware(['auth', 'nocache', 'role:faculty|viewer|super_admin'])
    ->delete('/documents/{document}', [DocumentController::class, 'destroy'])
    ->name('documents.destroy');

/*
|--------------------------------------------------------------------------
| College dean / unit head — dashboard & approval queue
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'nocache', 'role:college_dean|unit_head|super_admin'])
    ->group(function () {
        Route::get('/dean/dashboard', [DeanController::class, 'dashboard'])->name('dean.dashboard');
    });

Route::middleware(['auth', 'nocache', 'role:college_dean|unit_head|super_admin'])
    ->prefix('approval')
    ->group(function () {
        Route::get('/queue', [ApprovalController::class, 'queue'])->name('approval.queue');
        Route::get('/{research}/documents/{document}/download', [FileController::class, 'download'])->name('approval.documents.download');
        Route::get('/research/{research}/documents/{document}/preview', [ApprovalFileController::class, 'preview'])->name('approval.documents.preview');
        Route::post('/{research}/endorse', [ApprovalController::class, 'endorse'])->name('approval.endorse');
        Route::post('/{research}/return', [ApprovalController::class, 'returnSubmission'])->name('approval.return');
        Route::post('/{research}/reject', [ApprovalController::class, 'reject'])->name('approval.reject');
        Route::get('/{research}', [ApprovalController::class, 'review'])->name('approval.review');
    });

/*
|--------------------------------------------------------------------------
| OVPRI / CDAIC
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'nocache', 'role:ovpri_admin|cdaic_admin|super_admin'])
    ->prefix('ovpri')
    ->group(function () {
        Route::get('/dashboard', [OvpriController::class, 'dashboard'])->name('ovpri.dashboard');
        Route::get('/queue', [ApprovalController::class, 'ovpriQueue'])->name('ovpri.queue');
        Route::get('/review/{research}/documents/{document}/download', [FileController::class, 'download'])->name('ovpri.documents.download');
        Route::get('/review/{research}/documents/{document}/preview', [FileController::class, 'preview'])->name('ovpri.documents.preview');
        Route::get('/review/{research}', [OvpriController::class, 'review'])->name('ovpri.review');
        Route::post('/approve/{research}', [ApprovalController::class, 'approve'])->name('ovpri.approve');
        Route::post('/return/{research}', [ApprovalController::class, 'ovpriReturn'])->name('ovpri.return');
        Route::post('/reject/{research}', [ApprovalController::class, 'ovpriReject'])->name('ovpri.reject');
        Route::get('/research', [ResearchController::class, 'allResearch'])->name('ovpri.research');
    });

/*
|--------------------------------------------------------------------------
| Reports — university (OVPRI/super admin) or college scope (dean / unit head)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'nocache', 'role:ovpri_admin|cdaic_admin|super_admin|college_dean|unit_head'])
    ->prefix('reports')
    ->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');
        Route::post('/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/download/{token}', [ReportController::class, 'download'])->name('reports.download');
    });

/*
|--------------------------------------------------------------------------
| Super admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'nocache', 'role:super_admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('dashboard', function (Request $request) {
            $dateFrom = $request->filled('date_from') ? $request->input('date_from') : null;
            $dateTo = $request->filled('date_to') ? $request->input('date_to') : null;

            $totalUsers = User::count();
            $totalColleges = College::where('is_active', true)->count();

            $applyDates = function ($query) use ($dateFrom, $dateTo) {
                return $query
                    ->when($dateFrom, fn ($q) => $q->whereDate('start_date', '>=', $dateFrom))
                    ->when($dateTo, fn ($q) => $q->whereDate('start_date', '<=', $dateTo));
            };

            if (! Schema::hasTable('research')) {
                $emptyMonthly = ['labels' => [], 'counts' => []];
                for ($i = 5; $i >= 0; $i--) {
                    $emptyMonthly['labels'][] = now()->subMonths($i)->format('M Y');
                    $emptyMonthly['counts'][] = 0;
                }

                $classificationEmpty = [
                    'labels' => ['Internally funded', 'Self funded', 'Externally funded', 'Thesis', 'Other'],
                    'counts' => [0, 0, 0, 0, 0],
                    'colors' => ['#1E3A8A', '#D4AF37', '#059669', '#2563EB', '#94A3B8'],
                ];

                $researchByStatus = [
                    'draft' => 0,
                    'dean_review' => 0,
                    'ovpri_review' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                ];

                return view('admin.dashboard', [
                    'totalUsers' => $totalUsers,
                    'totalColleges' => $totalColleges,
                    'totalResearch' => 0,
                    'pendingApprovals' => 0,
                    'submissionsThisYear' => 0,
                    'researchByCollege' => [],
                    'researchByStatus' => $researchByStatus,
                    'researchByStage' => [
                        'labels' => ['Draft', 'Dean review', 'OVPRI review', 'Approved', 'Rejected'],
                        'counts' => [0, 0, 0, 0, 0],
                    ],
                    'researchByClassification' => $classificationEmpty,
                    'monthlySubmissions' => $emptyMonthly,
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ]);
            }

            // Match reports default scope: Eloquent (excludes soft-deletes) and exclude rejected.
            $activeResearch = fn () => $applyDates(Research::query()->where('approval_stage', '!=', 'rejected'));

            $totalResearch = (int) $activeResearch()->count();

            $researchByStatus = [
                'draft' => (int) $applyDates(Research::query()->where('approval_stage', 'draft'))->count(),
                'dean_review' => (int) $applyDates(Research::query()->where('approval_stage', 'dean_review'))->count(),
                'ovpri_review' => (int) $applyDates(Research::query()->where('approval_stage', 'ovpri_review'))->count(),
                'approved' => (int) $applyDates(Research::query()->where('approval_stage', 'approved'))->count(),
                'rejected' => (int) $applyDates(Research::query()->where('approval_stage', 'rejected'))->count(),
            ];

            $pendingApprovals = (int) $applyDates(
                Research::query()->whereIn('approval_stage', ['dean_review', 'ovpri_review'])
            )->count();

            $researchByCollege = College::query()
                ->orderBy('code')
                ->get()
                ->map(fn (College $college) => [
                    'label' => $college->code,
                    'count' => (int) $applyDates(
                        Research::query()
                            ->where('mother_college_id', $college->id)
                            ->where('approval_stage', '!=', 'rejected')
                    )->count(),
                ])
                ->values();

            $statusKeys = ['draft', 'dean_review', 'ovpri_review', 'approved', 'rejected'];
            $statusLabels = ['Draft', 'Dean review', 'OVPRI review', 'Approved', 'Rejected'];
            $statusCounts = $applyDates(Research::query())
                ->select('approval_stage', DB::raw('count(*) as total'))
                ->groupBy('approval_stage')
                ->pluck('total', 'approval_stage');

            $researchByStage = [
                'labels' => $statusLabels,
                'counts' => array_map(
                    fn (string $key) => (int) ($statusCounts[$key] ?? 0),
                    $statusKeys
                ),
            ];

            $classificationKeys = ['internally_funded', 'self_funded', 'externally_funded', 'thesis', 'other'];
            $classificationColorsMap = [
                'internally_funded' => '#1E3A8A',
                'self_funded' => '#D4AF37',
                'externally_funded' => '#059669',
                'thesis' => '#2563EB',
                'other' => '#94A3B8',
            ];
            $classificationLabelsMap = [
                'internally_funded' => 'Internally funded',
                'self_funded' => 'Self funded',
                'externally_funded' => 'Externally funded',
                'thesis' => 'Thesis',
                'other' => 'Other',
            ];

            $rawClass = $activeResearch()
                ->select('research_classification', DB::raw('count(*) as total'))
                ->groupBy('research_classification')
                ->pluck('total', 'research_classification');

            $primaryClassKeys = ['internally_funded', 'self_funded', 'externally_funded', 'thesis'];
            $mergedClassCounts = [];
            foreach ($primaryClassKeys as $key) {
                $mergedClassCounts[$key] = (int) ($rawClass[$key] ?? 0);
            }
            $otherTotal = (int) ($rawClass['other'] ?? 0);
            foreach ($rawClass as $key => $total) {
                if (! in_array($key, array_merge($primaryClassKeys, ['other']), true)) {
                    $otherTotal += (int) $total;
                }
            }
            $mergedClassCounts['other'] = $otherTotal;

            $researchByClassification = [
                'labels' => array_map(fn (string $k) => $classificationLabelsMap[$k], $classificationKeys),
                'counts' => array_map(fn (string $k) => $mergedClassCounts[$k], $classificationKeys),
                'colors' => array_map(fn (string $k) => $classificationColorsMap[$k], $classificationKeys),
            ];

            $submissionsThisYear = (int) $applyDates($activeResearch())
                ->when(! $dateFrom && ! $dateTo, fn ($q) => $q->whereYear('created_at', now()->year))
                ->count();

            $monthlySubmissions = Cache::remember(
                'admin_monthly_stats_'.($dateFrom ?? 'all').'_'.($dateTo ?? 'all').'_'.now()->format('Y-m'),
                3600,
                function () use ($applyDates, $dateFrom, $dateTo) {
                    $isSqlite = DB::connection()->getDriverName() === 'sqlite';
                    $base = $applyDates(Research::query()->where('approval_stage', '!=', 'rejected'));
                    $dateColumn = ($dateFrom || $dateTo) ? 'start_date' : 'created_at';
                    $byMonth = $isSqlite
                        ? (clone $base)
                            ->selectRaw("CAST(strftime('%m', {$dateColumn}) AS INTEGER) as month, count(*) as total")
                            ->when(! $dateFrom && ! $dateTo, fn ($q) => $q->whereYear('created_at', date('Y')))
                            ->groupByRaw("CAST(strftime('%m', {$dateColumn}) AS INTEGER)")
                            ->pluck('total', 'month')
                        : (clone $base)
                            ->selectRaw("MONTH({$dateColumn}) as month, count(*) as total")
                            ->when(! $dateFrom && ! $dateTo, fn ($q) => $q->whereYear('created_at', date('Y')))
                            ->groupByRaw("MONTH({$dateColumn})")
                            ->pluck('total', 'month');

                    $year = $dateFrom
                        ? (int) date('Y', strtotime((string) $dateFrom))
                        : (int) date('Y');
                    $monthlyLabels = [];
                    $monthlyCounts = [];
                    for ($m = 1; $m <= 12; $m++) {
                        $monthlyLabels[] = date('M Y', mktime(0, 0, 0, $m, 1, $year));
                        $monthlyCounts[] = (int) ($byMonth[$m] ?? $byMonth[(string) $m] ?? 0);
                    }

                    return [
                        'labels' => $monthlyLabels,
                        'counts' => $monthlyCounts,
                    ];
                }
            );

            return view('admin.dashboard', [
                'totalUsers' => $totalUsers,
                'totalColleges' => $totalColleges,
                'totalResearch' => $totalResearch,
                'pendingApprovals' => $pendingApprovals,
                'submissionsThisYear' => $submissionsThisYear,
                'researchByCollege' => $researchByCollege,
                'researchByStatus' => $researchByStatus,
                'researchByStage' => $researchByStage,
                'researchByClassification' => $researchByClassification,
                'monthlySubmissions' => $monthlySubmissions,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ]);
        })->name('admin.dashboard');

        Route::resource('users', UserController::class)->names('admin.users');
        Route::post('colleges/{college}/toggle-active', [CollegeController::class, 'toggleActive'])->name('admin.colleges.toggle-active');
        Route::resource('colleges', CollegeController::class)->names('admin.colleges');
        Route::resource('programs', ProgramController::class)
            ->only(['edit', 'update', 'store', 'destroy'])
            ->names('admin.programs');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('import/users', [ImportController::class, 'showUserImport'])->name('admin.import.users');
        Route::post('import/users', [ImportController::class, 'importUsers'])->name('admin.import.users.store');
        Route::get('import/research', [ImportController::class, 'showResearchImport'])->name('admin.import.research');
        Route::post('import/research', [ImportController::class, 'importResearch'])->name('admin.import.research.store');
    });
