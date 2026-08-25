<?php

use App\Models\College;
use App\Models\Document;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use App\Notifications\ResearchApproved;
use App\Notifications\ResearchApprovedDean;
use App\Notifications\ResearchEndorsed;
use App\Notifications\ResearchEndorsedToOvpri;
use App\Notifications\ResearchProgressUpdated;
use App\Notifications\ResearchSubmissionConfirmed;
use App\Notifications\ResearchReturned;
use App\Notifications\ResearchReturnedToDean;
use App\Notifications\ResearchSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use App\Support\ResearchStatus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// ResearchSubmitted
// ─────────────────────────────────────────────

describe('ResearchSubmitted', function () {

    it('is sent to the college dean of the research mother_college when faculty submits', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $dean = $college->headUser;

        $this->actingAs($faculty)->post(route('research.submit', $research));

        Notification::assertSentTo($dean, ResearchSubmitted::class);
    });

    it('is NOT sent to OVPRI admins on submit', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $ovpriA = makeOvpri();
        $ovpriB = makeOvpri();
        $research = makeDraftResearch($faculty, $college);

        $this->actingAs($faculty)->post(route('research.submit', $research));

        Notification::assertNotSentTo($ovpriA, ResearchSubmitted::class);
        Notification::assertNotSentTo($ovpriB, ResearchSubmitted::class);
    });

    it('is sent to the faculty submitter as a submission confirmation', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);

        $this->actingAs($faculty)->post(route('research.submit', $research));

        Notification::assertSentTo($faculty, ResearchSubmissionConfirmed::class);
        Notification::assertNotSentTo($faculty, ResearchSubmitted::class);
    });
});

// ─────────────────────────────────────────────
// ResearchEndorsed
// ─────────────────────────────────────────────

describe('ResearchEndorsed', function () {

    it('is sent to the primary author when the dean endorses', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)->post(route('approval.endorse', $research), ['remarks' => 'Endorsed.']);

        Notification::assertSentTo($faculty, ResearchEndorsed::class);
    });

    it('is NOT sent to the dean themselves', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)->post(route('approval.endorse', $research), ['remarks' => 'Endorsed.']);

        Notification::assertNotSentTo($dean, ResearchEndorsed::class);
    });
});

// ─────────────────────────────────────────────
// ResearchEndorsedToOvpri
// ─────────────────────────────────────────────

describe('ResearchEndorsedToOvpri', function () {

    it('is sent to every ovpri_admin user when the dean endorses', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $ovpriA = makeOvpri();
        $ovpriB = makeOvpri();
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)->post(route('approval.endorse', $research), ['remarks' => 'Endorsed.']);

        Notification::assertSentTo($ovpriA, ResearchEndorsedToOvpri::class);
        Notification::assertSentTo($ovpriB, ResearchEndorsedToOvpri::class);
    });

    it('is sent to every cdaic_admin user when the dean endorses', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $cdaicA = makeCdaic();
        $cdaicB = makeCdaic();
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)->post(route('approval.endorse', $research), ['remarks' => 'Endorsed.']);

        Notification::assertSentTo($cdaicA, ResearchEndorsedToOvpri::class);
        Notification::assertSentTo($cdaicB, ResearchEndorsedToOvpri::class);
    });

    it('is NOT sent to faculty on endorse (faculty receives ResearchEndorsed only)', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        makeOvpri();
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)->post(route('approval.endorse', $research), ['remarks' => 'Endorsed.']);

        Notification::assertNotSentTo($faculty, ResearchEndorsedToOvpri::class);
        Notification::assertSentTo($faculty, ResearchEndorsed::class);
    });
});

// ─────────────────────────────────────────────
// ResearchReturned (dean → draft)
// ─────────────────────────────────────────────

describe('ResearchReturned', function () {

    it('is sent to the primary author when the dean returns', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)->post(route('approval.return', $research), [
            'remarks' => 'Please revise Section 2 for clarity.',
        ]);

        Notification::assertSentTo($faculty, ResearchReturned::class);
    });

    it('is NOT sent to the dean on return', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)->post(route('approval.return', $research), [
            'remarks' => 'Please revise Section 2 for clarity.',
        ]);

        Notification::assertNotSentTo($dean, ResearchReturned::class);
    });

    it('is NOT sent to OVPRI users on dean return', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $ovpri = makeOvpri();
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW, 'submitted_at' => now()]);
        $dean = $college->headUser;

        $this->actingAs($dean)->post(route('approval.return', $research), [
            'remarks' => 'Please revise Section 2 for clarity.',
        ]);

        Notification::assertNotSentTo($ovpri, ResearchReturned::class);
        Notification::assertNotSentTo($ovpri, ResearchReturnedToDean::class);
    });
});

// ─────────────────────────────────────────────
// ResearchReturnedToDean (OVPRI return)
// ─────────────────────────────────────────────

describe('ResearchReturnedToDean', function () {

    it('is NOT sent to the college dean when OVPRI returns — faculty is notified instead', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_OVPRI_REVIEW, 'submitted_at' => now()]);
        $ovpri = makeOvpri();
        $dean = $college->headUser;

        $this->actingAs($ovpri)->post(route('ovpri.return', $research), [
            'remarks' => 'Needs additional documentation from the college.',
        ]);

        Notification::assertSentTo($faculty, ResearchReturned::class);
        Notification::assertNotSentTo($dean, ResearchReturnedToDean::class);
    });

    it('is sent to the primary author on OVPRI return — not as ResearchReturnedToDean', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_OVPRI_REVIEW, 'submitted_at' => now()]);
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)->post(route('ovpri.return', $research), [
            'remarks' => 'Needs additional documentation from the college.',
        ]);

        Notification::assertSentTo($faculty, ResearchReturned::class);
        Notification::assertNotSentTo($faculty, ResearchReturnedToDean::class);
    });
});

// ─────────────────────────────────────────────
// ResearchApproved
// ─────────────────────────────────────────────

describe('ResearchApproved', function () {

    it('is sent to the primary author when OVPRI approves', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_OVPRI_REVIEW, 'submitted_at' => now()]);
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)->post(route('ovpri.approve', $research), ['remarks' => 'Approved.']);

        Notification::assertSentTo($faculty, ResearchApproved::class);
    });
});

// ─────────────────────────────────────────────
// ResearchApprovedDean
// ─────────────────────────────────────────────

describe('ResearchApprovedDean', function () {

    it('is sent to the college dean when OVPRI approves', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_OVPRI_REVIEW, 'submitted_at' => now()]);
        $ovpri = makeOvpri();
        $dean = $college->headUser;

        $this->actingAs($ovpri)->post(route('ovpri.approve', $research), ['remarks' => 'Approved.']);

        Notification::assertSentTo($dean, ResearchApprovedDean::class);
    });

    it('is NOT sent to the OVPRI admin who performed the approval', function () {
        Notification::fake();
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_OVPRI_REVIEW, 'submitted_at' => now()]);
        $ovpri = makeOvpri();

        $this->actingAs($ovpri)->post(route('ovpri.approve', $research), ['remarks' => 'Approved.']);

        Notification::assertNotSentTo($ovpri, ResearchApprovedDean::class);
    });
});

// ─────────────────────────────────────────────
// ResearchProgressUpdated
// ─────────────────────────────────────────────

describe('ResearchProgressUpdated', function () {

    it('is sent to the college dean when faculty submits a progress update on approved research', function () {
        Notification::fake();
        Storage::fake('local');
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::ONGOING, 'research_registered_at' => now()]);
        $dean = $college->headUser;

        seedOutcomeClassifications();

        $this->actingAs($faculty)->put(route('research.update-progress', $research), [
            'outcome_classifications' => ['completed_not_presented_submitted'],
            'remarks' => 'Midterm update.',
            'external_link' => 'https://example.com/progress-proof',
        ]);

        Notification::assertSentTo($dean, ResearchProgressUpdated::class);
    });

    it('is NOT sent to OVPRI on progress update', function () {
        Notification::fake();
        Storage::fake('local');
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $ovpri = makeOvpri();
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::ONGOING, 'research_registered_at' => now()]);

        seedOutcomeClassifications();

        $this->actingAs($faculty)->put(route('research.update-progress', $research), [
            'outcome_classifications' => ['completed_not_presented_submitted'],
            'remarks' => 'Midterm update.',
            'external_link' => 'https://example.com/progress-proof',
        ]);

        Notification::assertNotSentTo($ovpri, ResearchProgressUpdated::class);
    });
});
