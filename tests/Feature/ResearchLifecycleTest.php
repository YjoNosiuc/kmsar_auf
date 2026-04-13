<?php

use App\Models\User;
use App\Models\College;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\Document;
use App\Models\Approval;
use App\Notifications\ResearchSubmitted;
use App\Notifications\ResearchEndorsed;
use App\Notifications\ResearchEndorsedToOvpri;
use App\Notifications\ResearchReturned;
use App\Notifications\ResearchReturnedToDean;
use App\Notifications\ResearchApproved;
use App\Notifications\ResearchApprovedDean;
use App\Notifications\ResearchRejectedDean;
use App\Notifications\ResearchProgressUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// SUITE 1 — WIZARD / DRAFT CREATION
// ─────────────────────────────────────────────

describe('Wizard: Draft Creation', function () {

    it('faculty can access the research create route and get redirected to wizard', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);

        $response = $this->actingAs($faculty)->get(route('research.create'));

        // Controller creates a draft then redirects to wizard step 1
        $response->assertRedirect();
        $this->assertDatabaseHas('research', [
            'primary_author_id' => $faculty->id,
            'approval_stage'    => 'draft',
        ]);
    });

    it('non-faculty roles cannot access the create route', function () {
        $college = makeCollege();
        $ovpri   = makeOvpri();

        $this->actingAs($ovpri)
            ->get(route('research.create'))
            ->assertForbidden();
    });

    it('faculty can save wizard step 1 (registration details)', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $payload = [
            'registration_type'         => 'new',
            'title'                     => 'Effects of AI in Philippine Education',
            'mother_college_id'         => $college->id,
            'research_classification'   => 'applied',
            'funding_agency'            => 'CHED',
            'sdg_tags'                  => [4, 8],
            'expected_output'           => ['publication'],
            'start_date'                => now()->toDateString(),
            'estimated_completion_date' => now()->addYear()->toDateString(),
            'status'                    => 'proposal',
        ];

        $this->actingAs($faculty)
            ->put(route('research.wizard.details.save', $research), $payload)
            ->assertRedirect(route('research.wizard.authors', $research));

        $this->assertDatabaseHas('research', [
            'id'    => $research->id,
            'title' => 'EFFECTS OF AI IN PHILIPPINE EDUCATION', // uppercase mutator
        ]);
    });

    it('wizard step 1 rejects missing required fields', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $this->actingAs($faculty)
            ->put(route('research.wizard.details.save', $research), [])
            ->assertSessionHasErrors(['title', 'registration_type', 'status']);
    });

    it('faculty can save wizard step 2 (authors)', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $payload = [
            'primary_author_type' => 'self',
            'authors' => [],
        ];

        $this->actingAs($faculty)
            ->post(route('research.wizard.authors.save', $research), $payload)
            ->assertRedirect(route('research.wizard.documents', $research));
    });

    it('faculty can view wizard step 3 (documents)', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $this->actingAs($faculty)
            ->get(route('research.wizard.documents', $research))
            ->assertOk()
            ->assertViewIs('faculty.research.documents');
    });

    it('faculty can delete a draft research record', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $this->actingAs($faculty)
            ->delete(route('research.destroy', $research))
            ->assertRedirect(route('research.index'));

        $this->assertSoftDeleted('research', ['id' => $research->id]);
    });

    it('faculty cannot delete research that is not in draft stage', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review']);

        $this->actingAs($faculty)
            ->delete(route('research.destroy', $research))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// SUITE 2 — SUBMIT (draft → dean_review)
// ─────────────────────────────────────────────

describe('Submit: draft → dean_review', function () {

    it('faculty can submit a draft research', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $dean     = $college->headUser;

        $this->actingAs($faculty)
            ->post(route('research.submit', $research))
            ->assertRedirect(route('research.show', $research));

        $research->refresh();
        expect($research->approval_stage)->toBe('dean_review');
        expect($research->submitted_at)->not->toBeNull();
    });

    it('submit sends ResearchSubmitted notification to college dean', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $dean     = $college->headUser;

        $this->actingAs($faculty)
            ->post(route('research.submit', $research));

        Notification::assertSentTo($dean, ResearchSubmitted::class);
    });

    it('submit fails when research has no documents', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage'    => 'draft',
        ]);
        // No Document records attached

        $this->actingAs($faculty)
            ->post(route('research.submit', $research))
            ->assertSessionHasErrors();

        expect($research->fresh()->approval_stage)->toBe('draft');
    });

    it('non-primary co-author without can_edit cannot submit', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $coauthor = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        ResearchAuthor::factory()->create([
            'research_id' => $research->id,
            'user_id'     => $coauthor->id,
            'is_primary'  => false,
            'can_edit'    => false,
        ]);

        $this->actingAs($coauthor)
            ->post(route('research.submit', $research))
            ->assertForbidden();
    });

    it('research cannot be submitted twice', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review']);

        $this->actingAs($faculty)
            ->post(route('research.submit', $research))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// SUITE 3 — DEAN ACTIONS
// ─────────────────────────────────────────────

describe('Dean: endorse / return / reject', function () {

    it('dean can endorse a research in dean_review stage', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $ovpri    = makeOvpri();
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review', 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)
            ->post(route('approval.endorse', $research), ['remarks' => 'Looks good.'])
            ->assertRedirect(route('approval.queue'));

        expect($research->fresh()->approval_stage)->toBe('ovpri_review');

        $this->assertDatabaseHas('approvals', [
            'research_id' => $research->id,
            'stage'       => 'dean',
            'action'      => 'endorsed',
        ]);
    });

    it('endorsing notifies primary author and OVPRI admins', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $ovpri    = makeOvpri();
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review', 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)
            ->post(route('approval.endorse', $research), ['remarks' => 'Endorsed.']);

        Notification::assertSentTo($faculty, ResearchEndorsed::class);
        Notification::assertSentTo($ovpri, ResearchEndorsedToOvpri::class);
    });

    it('dean cannot endorse research from a different college', function () {
        $college1 = makeCollege();
        $college2 = makeCollege(withDean: true);
        $faculty  = makeFaculty($college1);
        $research = makeDraftResearch($faculty, $college1);
        $research->update(['approval_stage' => 'dean_review']);
        $wrongDean = $college2->headUser;

        $this->actingAs($wrongDean)
            ->post(route('approval.endorse', $research), ['remarks' => 'Try.'])
            ->assertForbidden();
    });

    it('dean can return a research for revision', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review', 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)
            ->post(route('approval.return', $research), ['remarks' => 'Please revise Section 2.'])
            ->assertRedirect(route('approval.queue'));

        $research->refresh();
        expect($research->approval_stage)->toBe('draft');
        expect($research->revision_count)->toBe(1);
    });

    it('returning research notifies the primary author', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review', 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)
            ->post(route('approval.return', $research), ['remarks' => 'Please revise and resubmit this research.']);

        Notification::assertSentTo($faculty, ResearchReturned::class);
    });

    it('dean can reject research', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review', 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)
            ->post(route('approval.reject', $research), ['remarks' => 'Out of scope.'])
            ->assertRedirect(route('approval.queue'));

        expect($research->fresh()->approval_stage)->toBe('rejected');
    });

    it('rejection does NOT notify the primary author (only dean)', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review', 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)
            ->post(route('approval.reject', $research), ['remarks' => 'Out of scope.']);

        Notification::assertNotSentTo($faculty, \App\Notifications\ResearchRejected::class);
        Notification::assertSentTo($dean, ResearchRejectedDean::class);
    });

    it('dean cannot act on research already in ovpri_review', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'ovpri_review']);
        $dean = $college->headUser;

        $this->actingAs($dean)
            ->post(route('approval.endorse', $research), ['remarks' => 'Late endorse.'])
            ->assertForbidden();
    });

    it('faculty cannot access the dean queue', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);

        $this->actingAs($faculty)
            ->get(route('approval.queue'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// SUITE 4 — OVPRI ACTIONS
// ─────────────────────────────────────────────

describe('OVPRI: approve / return / reject', function () {

    it('OVPRI can approve research in ovpri_review', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'ovpri_review', 'submitted_at' => now()]);
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)
            ->post(route('ovpri.approve', $research), ['remarks' => 'Approved!'])
            ->assertRedirect(route('ovpri.queue'));

        expect($research->fresh()->approval_stage)->toBe('approved');

        $this->assertDatabaseHas('approvals', [
            'research_id' => $research->id,
            'stage'       => 'ovpri',
            'action'      => 'approved',
        ]);
    });

    it('approval notifies both primary author and college dean', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'ovpri_review']);
        $ovpri = makeOvpri();
        $dean  = $college->headUser;

        $this->actingAs($ovpri)
            ->post(route('ovpri.approve', $research), ['remarks' => 'Approved.']);

        Notification::assertSentTo($faculty, ResearchApproved::class);
        Notification::assertSentTo($dean, ResearchApprovedDean::class);
    });

    it('OVPRI can return research to dean_review', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'ovpri_review', 'submitted_at' => now()]);
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)
            ->post(route('ovpri.return', $research), ['remarks' => 'Needs more data.'])
            ->assertRedirect(route('ovpri.queue'));

        $research->refresh();
        expect($research->approval_stage)->toBe('dean_review');
        expect($research->revision_count)->toBe(1);
    });

    it('OVPRI return notifies dean only — NOT the primary author', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'ovpri_review']);
        $ovpri = makeOvpri();
        $dean  = $college->headUser;

        $this->actingAs($ovpri)
            ->post(route('ovpri.return', $research), ['remarks' => 'Please have the dean review this again.']);

        Notification::assertSentTo($dean, ResearchReturnedToDean::class);
        Notification::assertNotSentTo($faculty, ResearchReturned::class);
    });

    it('OVPRI can reject research', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'ovpri_review']);
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)
            ->post(route('ovpri.reject', $research), ['remarks' => 'Not aligned.'])
            ->assertRedirect(route('ovpri.queue'));

        expect($research->fresh()->approval_stage)->toBe('rejected');
    });

    it('OVPRI rejection notifies dean only', function () {
        Notification::fake();
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'ovpri_review']);
        $ovpri = makeOvpri();
        $dean  = $college->headUser;

        $this->actingAs($ovpri)
            ->post(route('ovpri.reject', $research), ['remarks' => 'Rejected.']);

        Notification::assertSentTo($dean, ResearchRejectedDean::class);
        Notification::assertNotSentTo($faculty, \App\Notifications\ResearchRejected::class);
    });

    it('OVPRI cannot approve research still in dean_review', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review']);
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)
            ->post(route('ovpri.approve', $research), ['remarks' => 'Early approve.'])
            ->assertForbidden();
    });

    it('faculty cannot access the OVPRI queue', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);

        $this->actingAs($faculty)
            ->get(route('ovpri.queue'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// SUITE 5 — POST-APPROVAL: REVISE & PROGRESS
// ─────────────────────────────────────────────

describe('Post-Approval: revise & progress update', function () {

    it('faculty can revise a rejected research (back to draft)', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'rejected']);

        $this->actingAs($faculty)
            ->post(route('research.revise', $research))
            ->assertRedirect(route('research.edit', $research));

        expect($research->fresh()->approval_stage)->toBe('draft');
    });

    it('faculty cannot revise research that is not rejected', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review']);

        $this->actingAs($faculty)
            ->post(route('research.revise', $research))
            ->assertForbidden();
    });

    it('faculty can submit a progress update on approved research', function () {
        Notification::fake();
        Storage::fake('local');
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'approved']);
        $dean = $college->headUser;

        $file = UploadedFile::fake()->createWithContent(
            'progress_report.pdf',
            minimalPdfBinary()
        );

        $this->actingAs($faculty)
            ->put(route('research.update-progress', $research), [
                'status'  => 'ongoing',
                'remarks' => 'Midterm progress report attached.',
                'files'   => [$file],
            ])
            ->assertRedirect(route('research.show', $research));

        $research->refresh();
        // Stage returns to dean_review for re-endorsement
        expect($research->approval_stage)->toBe('dean_review');

        $this->assertDatabaseHas('approvals', [
            'research_id' => $research->id,
            'stage'       => 'faculty',
            'action'      => 'progress_update',
        ]);
    });

    it('progress update notifies the college dean', function () {
        Notification::fake();
        Storage::fake('local');
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'approved']);
        $dean = $college->headUser;

        $this->actingAs($faculty)
            ->put(route('research.update-progress', $research), [
                'status'        => 'ongoing',
                'remarks'       => 'Update.',
                'external_link' => 'https://example.com/progress-proof',
            ]);

        Notification::assertSentTo($dean, ResearchProgressUpdated::class);
    });

    it('faculty cannot submit a progress update when research is not approved', function () {
        $college  = makeCollege();
        $faculty  = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['approval_stage' => 'dean_review']);

        $this->actingAs($faculty)
            ->put(route('research.update-progress', $research), ['status' => 'ongoing'])
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// SUITE 6 — FULL END-TO-END HAPPY PATH
// ─────────────────────────────────────────────

describe('Full Lifecycle: happy path end-to-end', function () {

    it('completes the full draft → submit → endorse → approve flow', function () {
        Notification::fake();
        Storage::fake('local');

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $dean    = $college->headUser;
        $ovpri   = makeOvpri();

        // 1. Create draft
        $this->actingAs($faculty)->get(route('research.create'));
        $research = Research::where('primary_author_id', $faculty->id)->latest()->first();
        expect($research->approval_stage)->toBe('draft');

        // 2. Fill wizard step 1
        $this->actingAs($faculty)->put(route('research.wizard.details.save', $research), [
            'registration_type'         => 'new',
            'title'                     => 'AI in Pampanga Schools',
            'mother_college_id'         => $college->id,
            'research_classification'   => 'applied',
            'sdg_tags'                  => [4],
            'expected_output'           => ['publication'],
            'start_date'                => now()->toDateString(),
            'estimated_completion_date' => now()->addMonths(6)->toDateString(),
            'status'                    => 'proposal',
        ]);

        // 3. Save authors
        $this->actingAs($faculty)->post(route('research.wizard.authors.save', $research), [
            'primary_author_type' => 'self',
            'authors' => [],
        ]);

        // 4. Upload document
        $file = UploadedFile::fake()->createWithContent('proposal.pdf', minimalPdfBinary());
        $this->actingAs($faculty)->post(route('documents.upload', $research), ['files' => [$file]]);
        expect($research->fresh()->documents()->count())->toBeGreaterThan(0);

        // 5. Submit
        $this->actingAs($faculty)->post(route('research.submit', $research));
        expect($research->fresh()->approval_stage)->toBe('dean_review');
        Notification::assertSentTo($dean, ResearchSubmitted::class);

        // 6. Dean endorses
        $this->actingAs($dean)->post(route('approval.endorse', $research), ['remarks' => 'Endorsed.']);
        expect($research->fresh()->approval_stage)->toBe('ovpri_review');
        Notification::assertSentTo($faculty, ResearchEndorsed::class);
        Notification::assertSentTo($ovpri, ResearchEndorsedToOvpri::class);

        // 7. OVPRI approves
        $this->actingAs($ovpri)->post(route('ovpri.approve', $research), ['remarks' => 'Approved!']);
        expect($research->fresh()->approval_stage)->toBe('approved');
        Notification::assertSentTo($faculty, ResearchApproved::class);
        Notification::assertSentTo($dean, ResearchApprovedDean::class);

        // Verify full approval trail
        $approvals = Approval::where('research_id', $research->id)->get();
        expect($approvals->where('stage', 'dean')->where('action', 'endorsed')->count())->toBe(1);
        expect($approvals->where('stage', 'ovpri')->where('action', 'approved')->count())->toBe(1);
    });
});
