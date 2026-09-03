<?php

declare(strict_types=1);

use App\Models\College;
use App\Models\Document;
use App\Models\OutcomeClassification;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use App\Support\ResearchStatus;
use Illuminate\Support\Facades\Storage;

/**
 * Shared Pest helpers for Feature tests (loaded once from Pest.php).
 */
function minimalPdfBinary(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
}

function makeCollege(bool $withDean = true): College
{
    $college = College::factory()->create(['is_active' => true]);

    if ($withDean) {
        $dean = User::factory()->create(['college_id' => $college->id, 'is_active' => true]);
        $dean->assignRole('college_dean');
        $college->update(['head_user_id' => $dean->id]);
        $college->setRelation('headUser', $dean);
    }

    return $college;
}

function makeFaculty(College $college): User
{
    $faculty = User::factory()->create(['college_id' => $college->id, 'is_active' => true]);
    $faculty->assignRole('faculty');

    return $faculty;
}

function makeViewer(College $college): User
{
    $viewer = User::factory()->create(['college_id' => $college->id, 'is_active' => true]);
    $viewer->assignRole('viewer');

    return $viewer;
}

function makeOvpri(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ovpri_admin');

    return $user;
}

function makeCdaic(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('cdaic_admin');

    return $user;
}

function seedOutcomeClassifications(): void
{
    (new \Database\Seeders\OutcomeClassificationSeeder)->run();
}

function makeDraftResearch(User $faculty, College $college): Research
{
    Storage::fake('local');

    $research = Research::factory()->create([
        'primary_author_id' => $faculty->id,
        'mother_college_id' => $college->id,
        'registration_type' => 'new',
        'status' => ResearchStatus::DRAFT,
        'sdg_tags' => [1, 4],
        'expected_output' => ['publication'],
    ]);

    ResearchAuthor::factory()->create([
        'research_id' => $research->id,
        'user_id' => $faculty->id,
        'is_primary' => true,
        'can_edit' => true,
        'first_name' => $faculty->first_name,
        'last_name' => $faculty->last_name,
    ]);

    Document::factory()->create([
        'research_id' => $research->id,
        'uploaded_by' => $faculty->id,
        'research_status_at_upload' => 'draft',
        'version' => 1,
    ]);

    return $research;
}

/**
 * @param  array<string, mixed>  $payload
 */
function submitResearchCompletion(Research $research, User $faculty, array $payload = []): \Illuminate\Testing\TestResponse
{
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('completion-proof.pdf', minimalPdfBinary());

    return test()->actingAs($faculty)->call(
        'PUT',
        route('research.update-progress', $research),
        array_merge([
            'outcome_classifications' => ['completed_not_presented_submitted'],
            'remarks' => 'Completion package attached.',
        ], $payload),
        [],
        ['files' => [$file]],
    );
}
