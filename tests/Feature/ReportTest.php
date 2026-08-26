<?php

/**
 * Verifies report generation and export in KMSAR.
 *
 * Rules:
 * - Only ovpri_admin, cdaic_admin, super_admin, college_dean, unit_head can access /reports.
 * - viewer and registrar are blocked by route middleware.
 * - Use Storage::fake('local') for export tests.
 * - Seed enough Research records per test to keep reports meaningful.
 */

use App\Models\Approval;
use App\Models\College;
use App\Models\OutcomeClassification;
use App\Models\Research;
use App\Models\User;
use App\Support\ResearchStatus;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────

function reportMakeUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'is_active' => true,
        'employee_number' => strtoupper(Str::random(10)),
        'first_name' => 'TEST',
        'last_name' => strtoupper(Str::random(6)),
    ], $attributes));
    $user->assignRole($role);

    return $user;
}

function reportAttachOutcome(Research $research, string $code = 'completed_not_presented_submitted', ?string $firstCompletedAt = null): void
{
    seedOutcomeClassifications();

    $classificationId = OutcomeClassification::query()->where('code', $code)->value('id');
    expect($classificationId)->not->toBeNull();

    $research->outcomeClassifications()->sync([$classificationId]);

    if ($research->first_completed_at === null) {
        $research->update([
            'first_completed_at' => $firstCompletedAt ?? now(),
        ]);
    }
}

/**
 * @return array{college: College, faculty: User, researches: \Illuminate\Support\Collection}
 */
function reportSeedCollegeResearchBundle(int $count = 5): array
{
    $college = College::factory()->create(['is_active' => true]);
    $faculty = User::factory()->create([
        'college_id' => $college->id,
        'is_active' => true,
        'employee_number' => strtoupper(Str::random(10)),
        'first_name' => 'FAC',
        'last_name' => 'ULTY',
    ]);
    $faculty->assignRole('faculty');

    $researches = collect();
    for ($i = 0; $i < $count; $i++) {
        $research = Research::factory()->create([
            'mother_college_id' => $college->id,
            'primary_author_id' => $faculty->id,
            'status' => ResearchStatus::RESEARCH_ACCEPTED,
            'research_registered_at' => now()->subMonth(),
            'research_accepted_at' => now(),
            'first_completed_at' => now()->subWeek(),
            'research_classification' => 'internally_funded',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        Approval::query()->create([
            'research_id' => $research->id,
            'approver_id' => $faculty->id,
            'stage' => 'ovpri',
            'review_cycle' => ResearchStatus::REVIEW_CYCLE_FINAL,
            'action' => 'approved',
            'acted_at' => $research->research_accepted_at ?? now(),
        ]);

        reportAttachOutcome($research);

        $researches->push($research);
    }

    return ['college' => $college, 'faculty' => $faculty, 'researches' => $researches];
}

// ─────────────────────────────────────────────
// ACCESS CONTROL
// ─────────────────────────────────────────────

describe('Reports access control', function () {

    beforeEach(function () {
        reportSeedCollegeResearchBundle(4);
    });

    it('ovpri_admin can access the reports index', function () {
        $user = reportMakeUser('ovpri_admin');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk();
    });

    it('cdaic_admin can access the reports index', function () {
        $user = reportMakeUser('cdaic_admin');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk();
    });

    it('super_admin can access the reports index', function () {
        $user = reportMakeUser('super_admin');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk();
    });

    it('college_dean can access the reports index', function () {
        $bundle = reportSeedCollegeResearchBundle(3);
        $dean = reportMakeUser('college_dean', ['college_id' => $bundle['college']->id]);

        $this->actingAs($dean)
            ->get(route('reports.index'))
            ->assertOk();
    });

    it('unit_head can access the reports index', function () {
        $bundle = reportSeedCollegeResearchBundle(3);
        $head = reportMakeUser('unit_head', ['college_id' => $bundle['college']->id]);

        $this->actingAs($head)
            ->get(route('reports.index'))
            ->assertOk();
    });

    it('viewer CANNOT access the reports index (blocked by middleware)', function () {
        $user = reportMakeUser('viewer');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    });

    it('registrar CANNOT access the reports index', function () {
        $user = reportMakeUser('registrar');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    });

    it('faculty CANNOT access the reports index', function () {
        $bundle = reportSeedCollegeResearchBundle(2);
        $faculty = reportMakeUser('faculty', ['college_id' => $bundle['college']->id]);

        $this->actingAs($faculty)
            ->get(route('reports.index'))
            ->assertForbidden();
    });

    it('guest is redirected to login', function () {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    });
});

// ─────────────────────────────────────────────
// PDF EXPORT
// ─────────────────────────────────────────────

describe('Reports PDF export', function () {

    it('ovpri_admin can export a PDF report', function () {
        Storage::fake('local');
        reportSeedCollegeResearchBundle(6);

        $user = reportMakeUser('ovpri_admin');

        $this->actingAs($user)
            ->post(route('reports.export'), [
                'report_type' => 'ovpri',
                'format' => 'pdf',
            ])
            ->assertOk();
    });

    it('college_dean can export a college-scoped PDF report', function () {
        Storage::fake('local');
        $bundle = reportSeedCollegeResearchBundle(5);
        $dean = reportMakeUser('college_dean', ['college_id' => $bundle['college']->id]);

        $this->actingAs($dean)
            ->post(route('reports.export'), [
                'report_type' => 'college',
                'format' => 'pdf',
            ])
            ->assertOk();
    });

    it('exported PDF response has correct Content-Type header', function () {
        Storage::fake('local');
        reportSeedCollegeResearchBundle(4);

        $user = reportMakeUser('ovpri_admin');

        $this->actingAs($user)
            ->post(route('reports.export'), [
                'report_type' => 'ovpri',
                'format' => 'pdf',
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });
});

// ─────────────────────────────────────────────
// EXCEL EXPORT
// ─────────────────────────────────────────────

describe('Reports Excel export', function () {

    it('ovpri_admin can export an Excel report', function () {
        Storage::fake('local');
        reportSeedCollegeResearchBundle(6);

        $user = reportMakeUser('ovpri_admin');

        $response = $this->actingAs($user)
            ->post(route('reports.export'), [
                'report_type' => 'ovpri',
                'format' => 'excel',
            ]);

        $response->assertRedirect();

        $this->actingAs($user)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    });

    it('exported Excel response has correct Content-Type header', function () {
        Storage::fake('local');
        reportSeedCollegeResearchBundle(4);

        $user = reportMakeUser('ovpri_admin');

        $response = $this->actingAs($user)
            ->post(route('reports.export'), [
                'report_type' => 'ovpri',
                'format' => 'excel',
            ]);

        $response->assertRedirect();

        $this->actingAs($user)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    });
});

// ─────────────────────────────────────────────
// COLLEGE SCOPING
// ─────────────────────────────────────────────

describe('Reports college scoping', function () {

    it('college_dean report only includes research from their own college', function () {
        $bundleA = reportSeedCollegeResearchBundle(5);
        $bundleB = reportSeedCollegeResearchBundle(5);

        $dean = reportMakeUser('college_dean', ['college_id' => $bundleA['college']->id]);

        $response = $this->actingAs($dean)
            ->get(route('reports.index'))
            ->assertOk();

        expect($response->viewData('reportScope'))->toBe('college')
            ->and($response->viewData('totalCount'))->toBe($bundleA['researches']->count())
            ->and(
                $response->viewData('preview')->every(
                    fn (Research $r) => (int) $r->mother_college_id === (int) $bundleA['college']->id
                )
            )->toBeTrue();
    });

    it('ovpri_admin report includes research from all colleges', function () {
        $bundleA = reportSeedCollegeResearchBundle(4);
        $bundleB = reportSeedCollegeResearchBundle(4);

        $user = reportMakeUser('ovpri_admin');

        $response = $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk();

        $expectedTotal = $bundleA['researches']->count() + $bundleB['researches']->count();
        $distinctMotherColleges = $response->viewData('preview')
            ->pluck('mother_college_id')
            ->unique()
            ->sort()
            ->values();

        expect($response->viewData('reportScope'))->toBe('ovpri')
            ->and($response->viewData('totalCount'))->toBe($expectedTotal)
            ->and($distinctMotherColleges->contains($bundleA['college']->id))->toBeTrue()
            ->and($distinctMotherColleges->contains($bundleB['college']->id))->toBeTrue();
    });
});

// ─────────────────────────────────────────────
// DRAFT EXCLUSION + ADMIN / OVPRI PARITY
// ─────────────────────────────────────────────

describe('Reports proposal exclusion', function () {

    it('never includes proposal-stage research for admin or OVPRI, even if proposal is requested', function () {
        $bundle = reportSeedCollegeResearchBundle(2);

        Research::factory()->create([
            'mother_college_id' => $bundle['college']->id,
            'primary_author_id' => $bundle['faculty']->id,
            'status' => ResearchStatus::DRAFT,
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);

        $admin = reportMakeUser('super_admin');
        $ovpri = reportMakeUser('ovpri_admin');

        $adminResponse = $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk();
        $ovpriResponse = $this->actingAs($ovpri)
            ->get(route('reports.index'))
            ->assertOk();

        expect($adminResponse->viewData('totalCount'))->toBe($bundle['researches']->count())
            ->and($ovpriResponse->viewData('totalCount'))->toBe($bundle['researches']->count())
            ->and($adminResponse->viewData('preview')->every(fn (Research $r) => $r->status !== ResearchStatus::DRAFT))->toBeTrue()
            ->and($ovpriResponse->viewData('preview')->every(fn (Research $r) => $r->status !== ResearchStatus::DRAFT))->toBeTrue();

        $forcedProposal = $this->actingAs($admin)
            ->get(route('reports.index', ['workflow_status' => ResearchStatus::DRAFT]))
            ->assertOk();

        expect($forcedProposal->viewData('preview')->every(fn (Research $r) => $r->status !== ResearchStatus::DRAFT))->toBeTrue()
            ->and($forcedProposal->viewData('totalCount'))->toBe($bundle['researches']->count());
    });

    it('counts the same in-progress and completed research on admin and OVPRI dashboards', function () {
        Illuminate\Support\Facades\Cache::flush();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);
        $ovpri = reportMakeUser('ovpri_admin');

        Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::DRAFT,
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        Research::factory()->deanReview()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now()->subWeek(),
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        $accepted = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::RESEARCH_ACCEPTED,
            'research_registered_at' => now()->subMonth(),
            'research_accepted_at' => now(),
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        reportAttachOutcome($accepted);

        $admin = reportMakeUser('super_admin');

        $adminView = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $ovpriView = $this->actingAs($ovpri)->get(route('ovpri.dashboard'))->assertOk();
        $reportsView = $this->actingAs($ovpri)->get(route('reports.index'))->assertOk();

        expect($adminView->viewData('researchInProgress'))->toBe(1)
            ->and($ovpriView->viewData('researchInProgress'))->toBe(1)
            ->and($adminView->viewData('totalResearch'))->toBe(1)
            ->and($ovpriView->viewData('totalResearch'))->toBe(1)
            ->and($reportsView->viewData('totalCount'))->toBe(1);
    });

    it('matches dashboard in-progress counts when reports filter proposal or ongoing', function () {
        Illuminate\Support\Facades\Cache::flush();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);
        $ovpri = reportMakeUser('ovpri_admin');

        Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::DRAFT,
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        Research::factory()->deanReview()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::INITIAL_DEAN_REVIEW,
            'title' => 'Pending dean initial review',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now()->subWeek(),
            'title' => 'Registered ongoing',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now()->subWeek(),
            'title' => 'Another ongoing',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);

        $ovpriView = $this->actingAs($ovpri)->get(route('ovpri.dashboard'))->assertOk();
        $draftReport = $this->actingAs($ovpri)->get(route('reports.index', ['status' => ResearchStatus::DRAFT]))->assertOk();
        $registeredReport = $this->actingAs($ovpri)->get(route('reports.index', ['workflow_status' => ResearchStatus::RESEARCH_REGISTERED]))->assertOk();

        expect($ovpriView->viewData('researchInProgress'))->toBe(2)
            ->and($draftReport->viewData('totalCount'))->toBe(0)
            ->and($registeredReport->viewData('totalCount'))->toBe(2);
    });

    it('excludes non-accepted research from total research counts', function () {
        Illuminate\Support\Facades\Cache::flush();
        seedOutcomeClassifications();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);
        $ovpri = reportMakeUser('ovpri_admin');
        $admin = reportMakeUser('super_admin');

        Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now()->subWeek(),
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);

        $withOutcome = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::FINAL_DEAN_REVIEW,
            'research_registered_at' => now()->subMonth(),
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        reportAttachOutcome($withOutcome);

        $accepted = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::RESEARCH_ACCEPTED,
            'research_registered_at' => now()->subMonth(),
            'research_accepted_at' => now(),
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        reportAttachOutcome($accepted);

        $adminView = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $ovpriView = $this->actingAs($ovpri)->get(route('ovpri.dashboard'))->assertOk();
        $reportsView = $this->actingAs($ovpri)->get(route('reports.index'))->assertOk();

        expect($adminView->viewData('totalResearch'))->toBe(1)
            ->and($ovpriView->viewData('totalResearch'))->toBe(1)
            ->and($reportsView->viewData('totalCount'))->toBe(1);
    });

    it('includes only research accepted records in default report totals', function () {
        Illuminate\Support\Facades\Cache::flush();
        seedOutcomeClassifications();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);
        $ovpri = reportMakeUser('ovpri_admin');

        foreach ([
            ResearchStatus::FINAL_DEAN_REVIEW,
            ResearchStatus::FINAL_REJECTED,
            ResearchStatus::RESEARCH_ACCEPTED,
        ] as $status) {
            $research = Research::factory()->create([
                'primary_author_id' => $faculty->id,
                'mother_college_id' => $college->id,
                'status' => $status,
                'research_registered_at' => now()->subMonth(),
                'research_accepted_at' => $status === ResearchStatus::RESEARCH_ACCEPTED ? now() : null,
                'research_classification' => 'internally_funded',
                'sdg_tags' => [4],
                'expected_output' => ['publication'],
            ]);
            reportAttachOutcome($research);
        }

        $reportsView = $this->actingAs($ovpri)->get(route('reports.index'))->assertOk();

        expect($reportsView->viewData('totalCount'))->toBe(1);
    });

    it('excludes dean-review records without outcome classifications from dashboard and report totals', function () {
        Illuminate\Support\Facades\Cache::flush();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);
        $ovpri = reportMakeUser('ovpri_admin');

        Research::factory()->deanReview()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::INITIAL_DEAN_REVIEW,
            'title' => 'Completed but not accepted',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);

        $ovpriView = $this->actingAs($ovpri)->get(route('ovpri.dashboard'))->assertOk();
        $reportsView = $this->actingAs($ovpri)->get(route('reports.index'))->assertOk();

        expect($ovpriView->viewData('totalResearch'))->toBe(0)
            ->and($reportsView->viewData('totalCount'))->toBe(0);
    });

    it('admin pending approvals only counts submitted dean and OVPRI review records', function () {
        Illuminate\Support\Facades\Cache::flush();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);
        $attrs = [
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ];

        Research::factory()->create(array_merge($attrs, [
            'status' => ResearchStatus::INITIAL_DEAN_REVIEW,
            'submitted_at' => null,
        ]));
        Research::factory()->deanReview()->create($attrs);
        Research::factory()->ovpriReview()->create($attrs);

        $admin = reportMakeUser('super_admin');
        $view = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        expect($view->viewData('pendingApprovals'))->toBe(2);
    });
});

describe('Reports date filter uses research_accepted_at for accepted totals', function () {

    it('includes all accepted records when no dates are set, and filters by research_accepted_at when they are', function () {
        $bundle = reportSeedCollegeResearchBundle(1);
        $inside = $bundle['researches']->first();
        $inside->update([
            'title' => 'INSIDE ACCEPTANCE WINDOW',
            'research_accepted_at' => '2025-03-15 10:00:00',
        ]);

        $outside = Research::factory()->create([
            'primary_author_id' => $bundle['faculty']->id,
            'mother_college_id' => $bundle['college']->id,
            'status' => ResearchStatus::RESEARCH_ACCEPTED,
            'research_registered_at' => '2024-06-01 09:00:00',
            'research_accepted_at' => '2024-01-10 10:00:00',
            'start_date' => '2025-06-01',
            'research_classification' => 'internally_funded',
            'title' => 'OUTSIDE ACCEPTANCE WINDOW',
            'created_at' => '2024-06-01 09:00:00',
        ]);
        reportAttachOutcome($outside, 'completed_not_presented_submitted', '2024-01-10 10:00:00');

        Research::factory()->create([
            'primary_author_id' => $bundle['faculty']->id,
            'mother_college_id' => $bundle['college']->id,
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now()->subMonth(),
            'research_classification' => 'internally_funded',
            'title' => 'NOT YET ACCEPTED',
        ]);

        $user = reportMakeUser('ovpri_admin');

        $allYears = $this->actingAs($user)->get(route('reports.index'))->assertOk();
        expect($allYears->viewData('totalCount'))->toBe(2)
            ->and($allYears->viewData('preview')->pluck('title'))
            ->not->toContain('NOT YET ACCEPTED');

        $filtered = $this->actingAs($user)
            ->get(route('reports.index', [
                'date_from' => '2025-03-01',
                'date_to' => '2025-03-31',
            ]))
            ->assertOk();

        expect($filtered->viewData('totalCount'))->toBe(1)
            ->and($filtered->viewData('preview')->pluck('title')->all())
            ->toBe(['INSIDE ACCEPTANCE WINDOW']);
    });
});

describe('Dean accepted research parity', function () {

    it('includes newly OVPRI-approved research on dean dashboard and reports', function () {
        Illuminate\Support\Facades\Cache::flush();

        $college = College::factory()->create(['is_active' => true]);
        $faculty = User::factory()->create([
            'college_id' => $college->id,
            'is_active' => true,
        ]);
        $faculty->assignRole('faculty');

        $dean = reportMakeUser('college_dean', ['college_id' => $college->id]);
        $ovpri = reportMakeUser('ovpri_admin');

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::FINAL_OVPRI_REVIEW,
            'submitted_at' => now()->subWeek(),
            'research_registered_at' => now()->subMonth(),
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
            'title' => 'DEAN PARITY FINAL APPROVAL',
        ]);

        $this->actingAs($ovpri)
            ->post(route('ovpri.approve', $research), ['remarks' => 'Final approval for dean parity.'])
            ->assertRedirect();

        expect($research->fresh()->status)->toBe(ResearchStatus::RESEARCH_ACCEPTED)
            ->and($research->fresh()->research_accepted_at)->not->toBeNull();

        $deanDashboard = $this->actingAs($dean)->get(route('dean.dashboard'))->assertOk();
        $deanReports = $this->actingAs($dean)
            ->get(route('reports.index', ['workflow_status' => ResearchStatus::RESEARCH_ACCEPTED]))
            ->assertOk();

        expect($deanDashboard->viewData('totalResearch'))->toBe(1)
            ->and($deanReports->viewData('totalCount'))->toBe(1)
            ->and($deanReports->viewData('preview')->pluck('title'))
            ->toContain('DEAN PARITY FINAL APPROVAL');
    });

    it('includes affiliated-college research in dean reports and dashboard totals', function () {
        Illuminate\Support\Facades\Cache::flush();

        $motherCollege = College::factory()->create(['is_active' => true, 'code' => 'CCS']);
        $affiliatedCollege = College::factory()->create(['is_active' => true, 'code' => 'CED']);
        $faculty = User::factory()->create([
            'college_id' => $motherCollege->id,
            'is_active' => true,
        ]);
        $faculty->assignRole('faculty');

        $dean = reportMakeUser('college_dean', ['college_id' => $affiliatedCollege->id]);

        Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $motherCollege->id,
            'other_college_id' => [$affiliatedCollege->id],
            'status' => ResearchStatus::RESEARCH_ACCEPTED,
            'research_registered_at' => now()->subMonth(),
            'research_accepted_at' => now()->subDay(),
            'research_classification' => 'internally_funded',
            'title' => 'AFFILIATED COLLEGE ACCEPTED',
        ]);

        $deanDashboard = $this->actingAs($dean)->get(route('dean.dashboard'))->assertOk();
        $deanReports = $this->actingAs($dean)->get(route('reports.index'))->assertOk();

        expect($deanDashboard->viewData('totalResearch'))->toBe(1)
            ->and($deanReports->viewData('totalCount'))->toBe(1)
            ->and($deanReports->viewData('preview')->pluck('title'))
            ->toContain('AFFILIATED COLLEGE ACCEPTED');
    });
});

describe('Dashboard outcome stat cards count by pivot code', function () {

    it('counts published, presented, and scopus cards from outcome classifications not legacy status', function () {
        Illuminate\Support\Facades\Cache::flush();
        seedOutcomeClassifications();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);
        $dean = reportMakeUser('college_dean', ['college_id' => $college->id]);
        $ovpri = reportMakeUser('ovpri_admin');

        $multiOutcome = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::RESEARCH_ACCEPTED,
            'research_registered_at' => now()->subMonth(),
            'research_accepted_at' => now()->subWeek(),
            'first_completed_at' => now()->subWeek(),
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        $classificationIds = OutcomeClassification::query()
            ->whereIn('code', ['presented_conference_auf', 'published_scopus_isi'])
            ->pluck('id')
            ->all();
        $multiOutcome->outcomeClassifications()->sync($classificationIds);

        Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now()->subWeek(),
            'research_classification' => 'internally_funded',
        ]);

        $nonAcceptedWithScopus = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::FINAL_DEAN_REVIEW,
            'research_registered_at' => now()->subMonth(),
            'first_completed_at' => now()->subWeek(),
            'research_classification' => 'internally_funded',
            'is_scopus_indexed' => true,
        ]);
        reportAttachOutcome($nonAcceptedWithScopus, 'published_scopus_isi');

        $deanView = $this->actingAs($dean)->get(route('dean.dashboard'))->assertOk();
        $ovpriView = $this->actingAs($ovpri)->get(route('ovpri.dashboard'))->assertOk();

        expect($deanView->viewData('presentedCount'))->toBe(1)
            ->and($deanView->viewData('scopusIndexedCount'))->toBe(1)
            ->and($ovpriView->viewData('scopusCount'))->toBe(1);

        $presentedByCollege = collect($ovpriView->viewData('presentedByCollege'));
        expect($presentedByCollege->firstWhere('label', $college->code)['count'] ?? 0)->toBe(1);
    });

    it('admin progress pie sums to accepted total using highest outcome per record', function () {
        Illuminate\Support\Facades\Cache::flush();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);
        $admin = reportMakeUser('super_admin');

        $accepted = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::RESEARCH_ACCEPTED,
            'research_accepted_at' => now(),
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        reportAttachOutcome($accepted, 'presented_conference_auf');

        $view = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $breakdown = collect($view->viewData('researchProgressBreakdown'));

        expect($view->viewData('totalResearch'))->toBe(1)
            ->and($breakdown->sum('count'))->toBe(1)
            ->and($breakdown->firstWhere('code', 'presented_conference_auf')['count'] ?? 0)->toBe(1);
    });
});
