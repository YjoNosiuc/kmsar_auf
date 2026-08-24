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
use App\Models\Research;
use App\Models\User;
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
        $research = Research::factory()->approved()->create([
            'mother_college_id' => $college->id,
            'primary_author_id' => $faculty->id,
            'status' => 'completed_unpublished',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        Approval::query()->create([
            'research_id' => $research->id,
            'approver_id' => $faculty->id,
            'stage' => 'ovpri',
            'action' => 'approved',
            'acted_at' => $research->created_at ?? now(),
        ]);

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

describe('Reports draft exclusion', function () {

    it('never includes draft research for admin or OVPRI, even if draft is requested', function () {
        $bundle = reportSeedCollegeResearchBundle(2);

        Research::factory()->create([
            'mother_college_id' => $bundle['college']->id,
            'primary_author_id' => $bundle['faculty']->id,
            'status' => 'completed_unpublished',
            'approval_stage' => 'draft',
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
            ->and($adminResponse->viewData('preview')->every(fn (Research $r) => $r->approval_stage !== 'draft'))->toBeTrue()
            ->and($ovpriResponse->viewData('preview')->every(fn (Research $r) => $r->approval_stage !== 'draft'))->toBeTrue();

        $forcedDraft = $this->actingAs($admin)
            ->get(route('reports.index', ['approval_stage' => 'draft']))
            ->assertOk();

        expect($forcedDraft->viewData('preview')->every(fn (Research $r) => $r->approval_stage !== 'draft'))->toBeTrue()
            ->and($forcedDraft->viewData('totalCount'))->toBe($bundle['researches']->count());
    });

    it('counts the same in-progress and completed research on admin and OVPRI dashboards', function () {
        Illuminate\Support\Facades\Cache::flush();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);

        Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'ongoing',
            'approval_stage' => 'draft',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        Research::factory()->deanReview()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'ongoing',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        Research::factory()->approved()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'ongoing',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        Research::factory()->deanReview()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'completed_unpublished',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);
        Research::factory()->approved()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'completed_unpublished',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ]);

        $admin = reportMakeUser('super_admin');
        $ovpri = reportMakeUser('ovpri_admin');

        $adminView = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $ovpriView = $this->actingAs($ovpri)->get(route('ovpri.dashboard'))->assertOk();

        expect($adminView->viewData('researchInProgress'))->toBe(2)
            ->and($ovpriView->viewData('researchInProgress'))->toBe(2)
            ->and($adminView->viewData('totalResearch'))->toBe(2)
            ->and($ovpriView->viewData('totalResearch'))->toBe(2);
    });

    it('admin pending approvals only counts submitted dean and OVPRI review records', function () {
        Illuminate\Support\Facades\Cache::flush();

        $college = makeCollege(false);
        $faculty = makeFaculty($college);
        $attrs = [
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'proposal',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
        ];

        Research::factory()->create(array_merge($attrs, [
            'approval_stage' => 'dean_review',
            'submitted_at' => null,
        ]));
        Research::factory()->deanReview()->create($attrs);
        Research::factory()->ovpriReview()->create($attrs);

        $admin = reportMakeUser('super_admin');
        $view = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        expect($view->viewData('pendingApprovals'))->toBe(2);
    });
});

describe('Reports date filter uses OVPRI approval', function () {

    it('includes all years when no dates are set, and filters by OVPRI approved date when they are', function () {
        $bundle = reportSeedCollegeResearchBundle(1);
        $inside = $bundle['researches']->first();
        $inside->update(['title' => 'INSIDE OVPRI APPROVAL WINDOW']);

        $outside = Research::factory()->approved()->create([
            'primary_author_id' => $bundle['faculty']->id,
            'mother_college_id' => $bundle['college']->id,
            'status' => 'completed_unpublished',
            'research_classification' => 'internally_funded',
            'title' => 'OUTSIDE OVPRI APPROVAL WINDOW',
            'created_at' => '2024-06-01 09:00:00',
        ]);

        Approval::query()
            ->where('research_id', $inside->id)
            ->where('stage', 'ovpri')
            ->where('action', 'approved')
            ->update(['acted_at' => '2025-03-15 10:00:00']);

        Approval::query()->create([
            'research_id' => $outside->id,
            'approver_id' => $bundle['faculty']->id,
            'stage' => 'ovpri',
            'action' => 'approved',
            'acted_at' => '2024-01-10 10:00:00',
        ]);

        Research::factory()->approved()->create([
            'primary_author_id' => $bundle['faculty']->id,
            'mother_college_id' => $bundle['college']->id,
            'status' => 'completed_unpublished',
            'research_classification' => 'internally_funded',
            'title' => 'NO OVPRI APPROVAL YET',
        ]);

        $user = reportMakeUser('ovpri_admin');

        $allYears = $this->actingAs($user)->get(route('reports.index'))->assertOk();
        expect($allYears->viewData('totalCount'))->toBe(2)
            ->and($allYears->viewData('preview')->pluck('title'))
            ->not->toContain('NO OVPRI APPROVAL YET');

        $filtered = $this->actingAs($user)
            ->get(route('reports.index', [
                'date_from' => '2025-03-01',
                'date_to' => '2025-03-31',
            ]))
            ->assertOk();

        expect($filtered->viewData('totalCount'))->toBe(1)
            ->and($filtered->viewData('preview')->pluck('title')->all())
            ->toBe(['INSIDE OVPRI APPROVAL WINDOW']);
    });
});
