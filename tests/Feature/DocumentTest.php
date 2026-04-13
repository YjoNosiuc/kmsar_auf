<?php

/**
 * Verifies document upload, download, preview, and deletion rules in KMSAR.
 *
 * Rules:
 * - Use Storage::fake('local') in every test that touches files
 * - Use Notification::fake() where submitting is involved
 * - Use UploadedFile::fake()->createWithContent('file.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF") for valid PDF uploads
 * - Never hardcode college codes
 */

use App\Models\College;
use App\Models\Document;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────

function documentTestMinimalPdfBinary(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
}

function documentTestValidPdfUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('file.pdf', documentTestMinimalPdfBinary());
}

/**
 * @return array{0: College, 1: User}
 */
function documentTestCollegeWithDean(): array
{
    $college = College::factory()->create(['is_active' => true]);
    $dean = User::factory()->create(['college_id' => $college->id, 'is_active' => true]);
    $dean->assignRole('college_dean');
    $college->update(['head_user_id' => $dean->id]);

    return [$college, $dean];
}

function documentTestFaculty(College $college): User
{
    $faculty = User::factory()->create(['college_id' => $college->id, 'is_active' => true]);
    $faculty->assignRole('faculty');

    return $faculty;
}

function documentTestCoAuthor(College $college): User
{
    $user = User::factory()->create(['college_id' => $college->id, 'is_active' => true]);
    $user->assignRole('co_author');

    return $user;
}

function documentTestRegistrar(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('registrar');

    return $user;
}

function documentTestOvpri(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ovpri_admin');

    return $user;
}

/**
 * Ensure bytes exist where FileController / DocumentController expect them (storage/app/...).
 */
function documentTestPutFileOnDisk(Document $document, ?string $content = null): void
{
    $content ??= documentTestMinimalPdfBinary();
    $full = storage_path('app/'.$document->disk_path);
    File::ensureDirectoryExists(dirname($full));
    File::put($full, $content);
}

// ─────────────────────────────────────────────
// UPLOAD
// ─────────────────────────────────────────────

describe('Document upload', function () {

    it('faculty can upload a document to their own draft research', function () {
        Storage::fake('local');

        [$college, $dean] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()
            ->linkedUser($faculty)
            ->for($research)
            ->primary()
            ->create();

        $this->actingAs($faculty)
            ->post(route('documents.upload', $research), [
                'files' => [documentTestValidPdfUpload()],
            ])
            ->assertRedirect();

        expect(Document::query()->where('research_id', $research->id)->exists())->toBeTrue();
    });

    it('co-author with can_edit can upload a document', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);
        $coAuthor = documentTestCoAuthor($college);

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();
        ResearchAuthor::factory()
            ->linkedUser($coAuthor)
            ->for($research)
            ->coAuthor(true)
            ->create();

        $this->actingAs($coAuthor)
            ->post(route('documents.upload', $research), [
                'files' => [documentTestValidPdfUpload()],
            ])
            ->assertRedirect();

        expect(Document::query()->where('research_id', $research->id)->where('uploaded_by', $coAuthor->id)->exists())->toBeTrue();
    });

    it('faculty cannot upload to another faculty research', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $owner = documentTestFaculty($college);
        $intruder = documentTestFaculty($college);

        $research = Research::factory()->create([
            'primary_author_id' => $owner->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($owner)->for($research)->primary()->create();

        $before = Document::query()->where('research_id', $research->id)->count();

        $this->actingAs($intruder)
            ->post(route('documents.upload', $research), [
                'files' => [documentTestValidPdfUpload()],
            ])
            ->assertForbidden();

        expect(Document::query()->where('research_id', $research->id)->count())->toBe($before);
    });

    it('upload fails if file fails FileValidationService validation (wrong mime)', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $fakePdfWithWrongBytes = UploadedFile::fake()->createWithContent('file.pdf', 'This is not PDF binary content.');

        $this->actingAs($faculty)
            ->from(route('research.wizard.documents', $research))
            ->post(route('documents.upload', $research), [
                'files' => [$fakePdfWithWrongBytes],
            ])
            ->assertRedirect(route('research.wizard.documents', $research))
            ->assertSessionHasErrors('files.0');
    });

    it('faculty can upload an external link instead of a file', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $url = 'https://example.org/research-paper.pdf';

        $this->actingAs($faculty)
            ->post(route('documents.upload', $research), [
                'external_link' => $url,
            ])
            ->assertRedirect();

        expect(Document::query()->where('research_id', $research->id)->where('external_link', $url)->exists())->toBeTrue();
    });
});

// ─────────────────────────────────────────────
// DELETE
// ─────────────────────────────────────────────

describe('Document delete', function () {

    it('faculty can delete a document from their own draft research', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/test-delete.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $this->actingAs($faculty)
            ->delete(route('documents.destroy', $document))
            ->assertRedirect();

        expect(Document::query()->whereKey($document->id)->exists())->toBeFalse();
    });

    it('faculty cannot delete a document from research not in draft stage', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->deanReview()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/locked.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $this->actingAs($faculty)
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();

        expect(Document::query()->whereKey($document->id)->exists())->toBeTrue();
    });

    it('faculty cannot delete another faculty document', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $owner = documentTestFaculty($college);
        $other = documentTestFaculty($college);

        $research = Research::factory()->create([
            'primary_author_id' => $owner->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($owner)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $owner->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/owner.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $this->actingAs($other)
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();

        expect(Document::query()->whereKey($document->id)->exists())->toBeTrue();
    });

    it('co-author cannot delete a document (only primary author can delete)', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);
        $coAuthor = documentTestCoAuthor($college);

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();
        ResearchAuthor::factory()
            ->linkedUser($coAuthor)
            ->for($research)
            ->coAuthor(true)
            ->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/primary-only.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $this->actingAs($coAuthor)
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();

        expect(Document::query()->whereKey($document->id)->exists())->toBeTrue();
    });
});

// ─────────────────────────────────────────────
// DOWNLOAD
// ─────────────────────────────────────────────

describe('Document download', function () {

    it('faculty can download their own research document', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/dl.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'original_filename' => 'DOWNLOAD.PDF',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $this->actingAs($faculty)
            ->get(route('documents.download', [$research, $document]))
            ->assertOk();
    });

    it('dean can download a document via the approval download route', function () {
        Storage::fake('local');

        [$college, $dean] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->deanReview()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/dean-dl.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'original_filename' => 'DEAN.PDF',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $this->actingAs($dean)
            ->get(route('approval.documents.download', [$research, $document]))
            ->assertOk();
    });

    it('OVPRI can download a document via the OVPRI download route', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);
        $ovpri = documentTestOvpri();

        $research = Research::factory()->ovpriReview()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/ovpri-dl.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'original_filename' => 'OVPRI.PDF',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $this->actingAs($ovpri)
            ->get(route('ovpri.documents.download', [$research, $document]))
            ->assertOk();
    });

    it('registrar cannot download a document that is not approved', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);
        $registrar = documentTestRegistrar();

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/registrar-blocked.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $this->actingAs($registrar);
        expect(Gate::forUser($registrar)->allows('view', $document))->toBeFalse();
    });

    it('unauthenticated user is redirected to login on download attempt', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/guest.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $this->get(route('documents.download', [$research, $document]))
            ->assertRedirect(route('login'));
    });
});

// ─────────────────────────────────────────────
// PREVIEW
// ─────────────────────────────────────────────

describe('Document preview', function () {

    it('faculty can preview their own document inline', function () {
        Storage::fake('local');

        [$college] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/preview.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'original_filename' => 'PREVIEW.PDF',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $response = $this->actingAs($faculty)
            ->get(route('documents.preview', [$research, $document]));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'inline; filename="PREVIEW.PDF"');
    });

    it('dean can preview via the approval preview route', function () {
        Storage::fake('local');

        [$college, $dean] = documentTestCollegeWithDean();
        $faculty = documentTestFaculty($college);

        $research = Research::factory()->deanReview()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => 'proposal',
            'sdg_tags' => [1, 4],
            'expected_output' => ['publication'],
        ]);

        ResearchAuthor::factory()->linkedUser($faculty)->for($research)->primary()->create();

        $document = Document::factory()->create([
            'research_id' => $research->id,
            'uploaded_by' => $faculty->id,
            'disk_path' => 'research_files/'.$college->id.'/'.$research->id.'/dean-preview.pdf',
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'original_filename' => 'DEANPREVIEW.PDF',
            'version' => 1,
        ]);
        documentTestPutFileOnDisk($document);

        $response = $this->actingAs($dean)
            ->get(route('approval.documents.preview', [$research, $document]));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'inline; filename="DEANPREVIEW.PDF"');
    });
});
