<?php

use App\Models\User;
use App\Models\College;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\Document;
use App\Models\Approval;
use App\Models\OutcomeClassification;
use App\Notifications\ResearchSubmitted;
use App\Notifications\ResearchEndorsed;
use App\Notifications\ResearchEndorsedToOvpri;
use App\Notifications\ResearchReturned;
use App\Notifications\ResearchReturnedToDean;
use App\Notifications\ResearchApproved;
use App\Notifications\ResearchApprovedDean;
use App\Notifications\ResearchRejected;
use App\Notifications\ResearchRejectedDean;
use App\Notifications\ResearchSubmissionConfirmed;
use App\Notifications\ResearchProgressUpdated;
use App\Support\ResearchStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Wizard: Draft Creation', function () {

    it('faculty can open the registration chooser without creating a draft', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);

        $this->actingAs($faculty)
            ->get(route('research.create'))
            ->assertOk()
            ->assertSee(__('Register new research'))
            ->assertSee(__('Register existing research'));

        $this->assertDatabaseCount('research', 0);
    });

    it('faculty can begin registration and get redirected to the wizard', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);

        $response = $this->actingAs($faculty)->post(route('research.begin'), [
            'registration_type' => 'new',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('research', [
            'primary_author_id' => $faculty->id,
            'status' => ResearchStatus::DRAFT,
        ]);
    });

    it('begin registration reuses an empty shell draft instead of creating duplicates', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);

        $this->actingAs($faculty)->post(route('research.begin'), [
            'registration_type' => 'new',
        ])->assertRedirect();

        $this->actingAs($faculty)->post(route('research.begin'), [
            'registration_type' => 'new',
        ])->assertRedirect();

        $this->assertDatabaseCount('research', 1);
    });

    it('faculty can save registration as draft with title only', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);

        $this->actingAs($faculty)->post(route('research.begin'), [
            'registration_type' => 'new',
        ])->assertRedirect();

        $research = Research::where('primary_author_id', $faculty->id)->latest()->first();

        $this->actingAs($faculty)
            ->put(route('research.wizard.details.save', $research), [
                'save_as_draft' => '1',
                'registration_type' => 'new',
                'title' => 'Draft Research Title Only',
            ])
            ->assertRedirect(route('research.index'))
            ->assertSessionHas('success');

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::DRAFT);
        expect($research->title)->toBe('Draft Research Title Only');

        $this->actingAs($faculty)
            ->get(route('research.index'))
            ->assertOk()
            ->assertSee('Draft Research Title Only', false);
    });

    it('hides empty-title proposal shells from My Research', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'status' => ResearchStatus::DRAFT,
            'title' => '',
        ]);

        $this->actingAs($faculty)
            ->get(route('research.index'))
            ->assertOk()
            ->assertDontSee($research->reference_number, false);
    });

    it('titled draft records are not visible to dean or OVPRI lists', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $dean = $college->headUser;
        $ovpri = makeOvpri();

        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'title' => 'Faculty-only draft title',
            'status' => ResearchStatus::DRAFT,
        ]);

        $this->actingAs($dean)
            ->get(route('approval.queue'))
            ->assertOk()
            ->assertDontSee('Faculty-only draft title', false);

        $this->actingAs($ovpri)
            ->get(route('ovpri.research'))
            ->assertOk()
            ->assertDontSee('Faculty-only draft title', false);
    });

    it('non-faculty roles cannot access the create route', function () {
        $college = makeCollege();
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)
            ->get(route('research.create'))
            ->assertForbidden();
    });

    it('faculty can save wizard step 1 (registration details)', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $payload = [
            'registration_type' => 'new',
            'title' => 'Effects of AI in Philippine Education',
            'mother_college_id' => $college->id,
            'research_classification' => 'internally_funded',
            'funding_agency' => 'CHED',
            'sdg_tags' => [4, 8],
            'expected_output' => ['publication'],
            'start_date' => now()->toDateString(),
            'estimated_completion_date' => now()->addYear()->toDateString(),
        ];

        $this->actingAs($faculty)
            ->put(route('research.wizard.details.save', $research), $payload)
            ->assertRedirect(route('research.wizard.authors', $research));

        $this->assertDatabaseHas('research', [
            'id' => $research->id,
            'title' => 'Effects of AI in Philippine Education',
        ]);
    });

    it('wizard step 1 rejects missing required fields', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $this->actingAs($faculty)
            ->put(route('research.wizard.details.save', $research), [])
            ->assertSessionHasErrors(['title', 'registration_type']);
    });

    it('faculty can save wizard step 2 (authors)', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $payload = [
            'primary_author_user_id' => $faculty->id,
            'coauthors' => [],
        ];

        $this->actingAs($faculty)
            ->post(route('research.wizard.authors.save', $research), $payload)
            ->assertRedirect(route('research.wizard.documents', $research));
    });

    it('faculty can view wizard step 3 (documents)', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $this->actingAs($faculty)
            ->get(route('research.wizard.documents', $research))
            ->assertOk()
            ->assertViewIs('faculty.research.documents');
    });

    it('faculty can delete a draft research record', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $this->actingAs($faculty)
            ->delete(route('research.destroy', $research))
            ->assertRedirect(route('research.index'));

        $this->assertSoftDeleted('research', ['id' => $research->id]);
    });

    it('faculty cannot delete research that is not in proposal stage', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW]);

        $this->actingAs($faculty)
            ->delete(route('research.destroy', $research))
            ->assertForbidden();
    });
});

describe('Submit: proposal → initial dean review', function () {

    it('faculty can submit a new research proposal', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $dean = $college->headUser;

        $this->actingAs($faculty)
            ->post(route('research.submit', $research))
            ->assertRedirect(route('research.show', $research));

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::INITIAL_DEAN_REVIEW);
        expect($research->submitted_at)->not->toBeNull();
    });

    it('existing registration skips initial review and becomes ongoing', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['registration_type' => 'existing']);

        $this->actingAs($faculty)
            ->post(route('research.submit', $research))
            ->assertRedirect(route('research.show', $research));

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::RESEARCH_REGISTERED);
        expect($research->research_registered_at)->not->toBeNull();
    });

    it('registered research locks details and authors but allows documents wizard', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now(),
            'submitted_at' => now(),
        ]);

        $this->actingAs($faculty)
            ->get(route('research.wizard.details', $research))
            ->assertForbidden();

        $this->actingAs($faculty)
            ->get(route('research.wizard.authors', $research))
            ->assertForbidden();

        $this->actingAs($faculty)
            ->get(route('research.wizard.documents', $research))
            ->assertOk()
            ->assertViewIs('faculty.research.documents');
    });

    it('submit sends ResearchSubmitted notification to college dean and confirmation to faculty', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $dean = $college->headUser;

        $this->actingAs($faculty)
            ->post(route('research.submit', $research));

        Notification::assertSentTo($dean, ResearchSubmitted::class);
        Notification::assertSentTo($faculty, ResearchSubmissionConfirmed::class);
    });

    it('submit fails when research has no documents', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = Research::factory()->create([
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'registration_type' => 'new',
            'status' => ResearchStatus::DRAFT,
        ]);

        $this->actingAs($faculty)
            ->post(route('research.submit', $research))
            ->assertSessionHasErrors();

        expect($research->fresh()->status)->toBe(ResearchStatus::DRAFT);
    });

    it('non-primary co-author without can_edit cannot submit', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $coauthor = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        ResearchAuthor::factory()->create([
            'research_id' => $research->id,
            'user_id' => $coauthor->id,
            'is_primary' => false,
            'can_edit' => false,
        ]);

        $this->actingAs($coauthor)
            ->post(route('research.submit', $research))
            ->assertForbidden();
    });

    it('research cannot be submitted twice', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW]);

        $this->actingAs($faculty)
            ->post(route('research.submit', $research))
            ->assertRedirect(route('research.show', $research))
            ->assertSessionHas('info');
    });
});

describe('Dean: endorse / return (initial cycle)', function () {

    it('dean can endorse research in initial dean review', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        makeOvpri();
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)
            ->post(route('approval.endorse', $research), ['remarks' => 'Looks good.'])
            ->assertRedirect(route('approval.queue', ['cycle' => 'initial']));

        expect($research->fresh()->status)->toBe(ResearchStatus::INITIAL_OVPRI_REVIEW);

        $this->assertDatabaseHas('approvals', [
            'research_id' => $research->id,
            'stage' => 'dean',
            'review_cycle' => ResearchStatus::REVIEW_CYCLE_INITIAL,
            'action' => 'endorsed',
        ]);
    });

    it('dean can return research for initial revision', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)
            ->post(route('approval.return', $research), ['remarks' => 'Please revise Section 2.'])
            ->assertRedirect(route('approval.queue', ['cycle' => 'initial']));

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::INITIAL_REJECTED);
        expect($research->revision_count)->toBe(1);
    });

    it('dean cannot endorse research from a different college', function () {
        $college1 = makeCollege();
        $college2 = makeCollege(withDean: true);
        $faculty = makeFaculty($college1);
        $research = makeDraftResearch($faculty, $college1);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW]);
        $wrongDean = $college2->headUser;

        $this->actingAs($wrongDean)
            ->post(route('approval.endorse', $research), ['remarks' => 'Try.'])
            ->assertForbidden();
    });

    it('routes dean queue and actions by mother college not author home college', function () {
        Notification::fake();

        $authorCollege = makeCollege(withDean: true);
        $motherCollege = makeCollege(withDean: true);
        $faculty = makeFaculty($authorCollege);
        $research = makeDraftResearch($faculty, $motherCollege);
        $research->update([
            'status' => ResearchStatus::INITIAL_DEAN_REVIEW,
            'submitted_at' => now(),
        ]);

        $motherDean = $motherCollege->headUser;
        $authorDean = $authorCollege->headUser;

        $this->actingAs($motherDean)
            ->get(route('approval.queue'))
            ->assertOk()
            ->assertSee($research->reference_number, false);

        $this->actingAs($authorDean)
            ->get(route('approval.queue'))
            ->assertOk()
            ->assertDontSee($research->reference_number, false);

        $this->actingAs($motherDean)
            ->post(route('approval.endorse', $research), ['remarks' => 'Endorsed to OVPRI.'])
            ->assertRedirect(route('approval.queue', ['cycle' => 'initial']));

        $this->actingAs($authorDean)
            ->post(route('approval.return', $research->fresh()), ['remarks' => 'Wrong dean attempt.'])
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

describe('OVPRI: approve / return (initial cycle)', function () {

    it('OVPRI can approve research in initial ovpri review', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_OVPRI_REVIEW, 'submitted_at' => now()]);
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)
            ->post(route('ovpri.approve', $research), ['remarks' => 'Approved!'])
            ->assertRedirect(route('ovpri.queue', ['cycle' => 'initial', 'tab' => 'approved']));

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::RESEARCH_REGISTERED);
        expect($research->research_registered_at)->not->toBeNull();

        $this->assertDatabaseHas('approvals', [
            'research_id' => $research->id,
            'stage' => 'ovpri',
            'review_cycle' => ResearchStatus::REVIEW_CYCLE_INITIAL,
            'action' => 'approved',
        ]);
    });

    it('OVPRI can return research during initial review', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_OVPRI_REVIEW, 'submitted_at' => now()]);
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)
            ->post(route('ovpri.return', $research), ['remarks' => 'Needs more data.'])
            ->assertRedirect(route('ovpri.queue', ['cycle' => 'initial', 'tab' => 'returned']));

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::INITIAL_REJECTED);
        expect($research->revision_count)->toBe(1);
    });

    it('OVPRI cannot approve research still in initial dean review', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW]);
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

describe('Resubmit and completion (final cycle)', function () {

    it('faculty can resubmit after initial rejection', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_REJECTED]);

        $this->actingAs($faculty)
            ->post(route('research.revise', $research))
            ->assertRedirect(route('research.show', $research));

        expect($research->fresh()->status)->toBe(ResearchStatus::INITIAL_DEAN_REVIEW);
    });

    it('faculty cannot resubmit when not in a returned status', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW]);

        $this->actingAs($faculty)
            ->post(route('research.revise', $research))
            ->assertForbidden();
    });

    it('faculty can submit completion from ongoing research', function () {
        Notification::fake();
        Storage::fake('local');
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now(),
        ]);
        $dean = $college->headUser;

        submitResearchCompletion($research, $faculty, [
            'remarks' => 'Completion package attached.',
            'external_link' => 'https://example.com/completion-proof',
        ])->assertRedirect(route('research.show', $research));

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::FINAL_DEAN_REVIEW);
        expect($research->first_completed_at)->not->toBeNull();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Research::class,
            'auditable_id' => $research->id,
            'action' => 'research.completion.research_completed',
        ]);

        $this->assertDatabaseHas('approvals', [
            'research_id' => $research->id,
            'stage' => 'faculty',
            'review_cycle' => ResearchStatus::REVIEW_CYCLE_FINAL,
            'action' => 'completion_submitted',
        ]);
    });

    it('dean approval queue shows completion submissions under final review', function () {
        Notification::fake();
        Storage::fake('local');
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $dean = $college->headUser;
        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now(),
        ]);

        submitResearchCompletion($research, $faculty, [
            'remarks' => 'Completion package attached.',
            'external_link' => 'https://example.com/completion-proof',
        ])->assertRedirect(route('research.show', $research));

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::FINAL_DEAN_REVIEW);

        $this->actingAs($dean)
            ->get(route('approval.queue'))
            ->assertOk()
            ->assertSee($research->reference_number, false);

        $this->actingAs($dean)
            ->get(route('approval.queue', ['cycle' => 'final']))
            ->assertOk()
            ->assertSee($research->reference_number, false);
    });

    it('completion submission notifies the college dean', function () {
        Notification::fake();
        Storage::fake('local');
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now(),
        ]);
        $dean = $college->headUser;

        submitResearchCompletion($research, $faculty, [
            'remarks' => 'Update.',
            'external_link' => 'https://example.com/progress-proof',
        ]);

        Notification::assertSentTo($dean, ResearchProgressUpdated::class);
    });

    it('rejects completion submission without at least one document', function () {
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now(),
        ]);

        $this->actingAs($faculty)
            ->from(route('research.show', $research))
            ->put(route('research.update-progress', $research), [
                'outcome_classifications' => ['completed_not_presented_submitted'],
                'external_link' => 'https://example.com/link-only',
            ])
            ->assertRedirect(route('research.show', $research))
            ->assertSessionHasErrors('files');
    });

    it('rejects disallowed links such as YouTube with a clear message on completion submit', function () {
        Storage::fake('local');
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now(),
        ]);

        $file = UploadedFile::fake()->createWithContent('proof.pdf', minimalPdfBinary());

        $response = $this->actingAs($faculty)
            ->from(route('research.show', $research))
            ->call('PUT', route('research.update-progress', $research), [
                'outcome_classifications' => ['completed_not_presented_submitted'],
                'external_links' => ['https://youtube.com/watch?v=test'],
            ], [], ['files' => [$file]]);

        $response->assertRedirect(route('research.show', $research));
        $response->assertSessionHasErrors('external_links.0');
        expect(session('errors')->first('external_links.0'))
            ->toContain('Google Drive');
    });

    it('rejects plain text such as abc as an invalid link', function () {
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now(),
        ]);

        $file = UploadedFile::fake()->createWithContent('proof.pdf', minimalPdfBinary());

        $this->actingAs($faculty)
            ->from(route('research.show', $research))
            ->call('PUT', route('research.update-progress', $research), [
                'outcome_classifications' => ['completed_not_presented_submitted'],
                'external_links' => ['abc'],
            ], [], ['files' => [$file]])
            ->assertRedirect(route('research.show', $research))
            ->assertSessionHasErrors('external_links.0');

        expect(session('errors')->first('external_links.0'))
            ->toContain('Invalid link');
    });

    it('allows multiple links and files together on completion submit', function () {
        Notification::fake();
        Storage::fake('local');
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now(),
        ]);

        $file = UploadedFile::fake()->createWithContent('proof.pdf', minimalPdfBinary());

        $this->actingAs($faculty)
            ->call('PUT', route('research.update-progress', $research), [
                'outcome_classifications' => ['completed_not_presented_submitted'],
                'external_links' => [
                    'https://drive.google.com/file/d/abc/view',
                    'https://doi.org/10.1000/example',
                ],
            ], [], ['files' => [$file]])
            ->assertRedirect(route('research.show', $research));

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::FINAL_DEAN_REVIEW);
        expect($research->documents()->whereNotNull('external_link')->count())->toBe(2);
        expect($research->documents()->whereNotNull('disk_path')->count())->toBeGreaterThanOrEqual(1);
    });

    it('faculty can resubmit final outcomes from final_rejected via update-progress', function () {
        Notification::fake();
        Storage::fake('local');
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::FINAL_REJECTED,
            'final_review_count' => 1,
            'research_registered_at' => now()->subMonth(),
        ]);

        $classificationId = OutcomeClassification::query()
            ->where('code', 'completed_not_presented_submitted')
            ->value('id');
        $research->outcomeClassifications()->sync([$classificationId]);

        $countBefore = (int) $research->final_review_count;

        submitResearchCompletion($research, $faculty, [
            'outcome_classifications' => ['presented_conference_auf'],
            'remarks' => 'Revised outcome package.',
            'external_link' => 'https://example.com/final-resubmit-proof',
        ])->assertRedirect(route('research.show', $research));

        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::FINAL_DEAN_REVIEW);
        expect($research->final_review_count)->toBe($countBefore);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Research::class,
            'auditable_id' => $research->id,
            'action' => 'research.completion.research_completed',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Research::class,
            'auditable_id' => $research->id,
            'action' => 'research.final.resubmitted',
        ]);
    });
});

describe('Full Lifecycle: new registration happy path', function () {

    it('completes proposal → initial review → ongoing registration', function () {
        Notification::fake();
        Storage::fake('local');

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $dean = $college->headUser;
        $ovpri = makeOvpri();

        $this->actingAs($faculty)->post(route('research.begin'), [
            'registration_type' => 'new',
        ])->assertRedirect();
        $research = Research::where('primary_author_id', $faculty->id)->latest()->first();
        expect($research->status)->toBe(ResearchStatus::DRAFT);

        $this->actingAs($faculty)->put(route('research.wizard.details.save', $research), [
            'registration_type' => 'new',
            'title' => 'AI in Pampanga Schools',
            'mother_college_id' => $college->id,
            'research_classification' => 'internally_funded',
            'sdg_tags' => [4],
            'expected_output' => ['publication'],
            'start_date' => now()->toDateString(),
            'estimated_completion_date' => now()->addMonths(6)->toDateString(),
        ]);

        $this->actingAs($faculty)->post(route('research.wizard.authors.save', $research), [
            'primary_author_user_id' => $faculty->id,
            'coauthors' => [],
        ]);

        $file = UploadedFile::fake()->createWithContent('proposal.pdf', minimalPdfBinary());
        $this->actingAs($faculty)->post(route('documents.upload', $research), ['files' => [$file]]);
        expect($research->fresh()->documents()->count())->toBeGreaterThan(0);

        $this->actingAs($faculty)->post(route('research.submit', $research));
        expect($research->fresh()->status)->toBe(ResearchStatus::INITIAL_DEAN_REVIEW);
        Notification::assertSentTo($dean, ResearchSubmitted::class);

        $this->actingAs($dean)->post(route('approval.endorse', $research), ['remarks' => 'Endorsed.']);
        expect($research->fresh()->status)->toBe(ResearchStatus::INITIAL_OVPRI_REVIEW);
        Notification::assertSentTo($faculty, ResearchEndorsed::class);
        Notification::assertSentTo($ovpri, ResearchEndorsedToOvpri::class);

        $this->actingAs($ovpri)->post(route('ovpri.approve', $research), ['remarks' => 'Approved!']);
        expect($research->fresh()->status)->toBe(ResearchStatus::RESEARCH_REGISTERED);
        Notification::assertSentTo($faculty, ResearchApproved::class);
        Notification::assertSentTo($dean, ResearchApprovedDean::class);

        $approvals = Approval::where('research_id', $research->id)->get();
        expect($approvals->where('stage', 'dean')->where('action', 'endorsed')->count())->toBe(1);
        expect($approvals->where('stage', 'ovpri')->where('action', 'approved')->count())->toBe(1);
    });
});

describe('Full Lifecycle: final acceptance path', function () {

    it('completes ongoing → final review → research accepted', function () {
        Notification::fake();
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $dean = $college->headUser;
        $ovpri = makeOvpri();

        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now()->subMonth(),
            'submitted_at' => now()->subMonth(),
        ]);

        submitResearchCompletion($research, $faculty, [
            'outcome_classifications' => ['published_scopus_isi'],
            'remarks' => 'Final outputs attached.',
            'external_link' => 'https://example.com/final-output',
        ]);
        expect($research->fresh()->status)->toBe(ResearchStatus::FINAL_DEAN_REVIEW);

        $this->actingAs($dean)->post(route('approval.endorse', $research), ['remarks' => 'Final endorse.']);
        expect($research->fresh()->status)->toBe(ResearchStatus::FINAL_OVPRI_REVIEW);

        $this->actingAs($ovpri)->post(route('ovpri.approve', $research), ['remarks' => 'Final approved.']);
        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::RESEARCH_ACCEPTED);
        expect($research->research_accepted_at)->not->toBeNull();
    });

    it('preserves first_completed_at when submitting a later completion from research accepted', function () {
        Notification::fake();
        Storage::fake('local');
        seedOutcomeClassifications();

        $college = makeCollege();
        $faculty = makeFaculty($college);
        $dean = $college->headUser;
        $ovpri = makeOvpri();

        $research = makeDraftResearch($faculty, $college);
        $research->update([
            'status' => ResearchStatus::RESEARCH_REGISTERED,
            'research_registered_at' => now()->subMonths(2),
            'submitted_at' => now()->subMonths(2),
        ]);

        submitResearchCompletion($research, $faculty, [
            'outcome_classifications' => ['completed_not_presented_submitted'],
            'remarks' => 'First completion.',
            'external_link' => 'https://example.com/first-completion',
        ]);

        $research->refresh();
        $firstCompletedAt = $research->first_completed_at;
        expect($firstCompletedAt)->not->toBeNull();

        $this->actingAs($dean)->post(route('approval.endorse', $research), ['remarks' => 'Endorse first completion.']);
        $this->actingAs($ovpri)->post(route('ovpri.approve', $research), ['remarks' => 'Accept first completion.']);
        $research->refresh();
        expect($research->status)->toBe(ResearchStatus::RESEARCH_ACCEPTED);

        $this->travel(1)->month();

        submitResearchCompletion($research, $faculty, [
            'outcome_classifications' => ['published_scopus_isi'],
            'remarks' => 'Progress update after acceptance.',
            'external_link' => 'https://example.com/second-completion',
        ]);

        $research->refresh();
        expect($research->first_completed_at?->toIso8601String())
            ->toBe($firstCompletedAt->toIso8601String());
    });
});
