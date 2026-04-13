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
        $researches->push(Research::factory()->approved()->create([
            'mother_college_id' => $college->id,
            'primary_author_id' => $faculty->id,
            'status' => 'ongoing',
            'research_classification' => 'internally_funded',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]));
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

        $this->actingAs($user)
            ->post(route('reports.export'), [
                'report_type' => 'ovpri',
                'format' => 'excel',
            ])
            ->assertOk();
    });

    it('exported Excel response has correct Content-Type header', function () {
        Storage::fake('local');
        reportSeedCollegeResearchBundle(4);

        $user = reportMakeUser('ovpri_admin');

        $this->actingAs($user)
            ->post(route('reports.export'), [
                'report_type' => 'ovpri',
                'format' => 'excel',
            ])
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
