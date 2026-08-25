<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\OutcomeClassification;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use App\Support\ResearchStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ResearchSeeder extends Seeder
{
    public function run(): void
    {
        $ccs = College::query()->where('code', 'CCS')->firstOrFail();
        $camp = College::query()->where('code', 'CAMP')->firstOrFail();

        $ccs1 = User::query()->where('email', 'faculty.ccs1@yopmail.com')->firstOrFail();
        $ccs2 = User::query()->where('email', 'faculty.ccs2@yopmail.com')->firstOrFail();
        $camp1 = User::query()->where('email', 'faculty.camp1@yopmail.com')->firstOrFail();
        $camp2 = User::query()->where('email', 'faculty.camp2@yopmail.com')->firstOrFail();

        $records = [
            // ── Drafts (not in institutional totals) ───────────────────────
            [
                'reference_number' => 'AUF-2025-CCS-0001',
                'title' => 'Natural Language Processing for Tagalog Sentiment Analysis',
                'primary_author_id' => $ccs1->id,
                'mother_college_id' => $ccs->id,
                'registration_type' => 'new',
                'research_classification' => 'student_thesis_dissertation',
                'agenda_themes' => ['theme_2', 'theme_4'],
                'status' => ResearchStatus::PROPOSAL,
                'sdg_tags' => [4, 9],
                'created_at' => '2025-09-08 11:45:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0001',
                'title' => 'Home-Based Physical Therapy Protocol for Post-Stroke Recovery',
                'primary_author_id' => $camp1->id,
                'mother_college_id' => $camp->id,
                'registration_type' => 'new',
                'research_classification' => 'student_thesis_dissertation',
                'agenda_themes' => ['theme_1', 'theme_4'],
                'status' => ResearchStatus::PROPOSAL,
                'sdg_tags' => [3, 10],
                'created_at' => '2025-09-12 08:30:00',
            ],

            // ── Initial review queue ─────────────────────────────────────────
            [
                'reference_number' => 'AUF-2025-CCS-0002',
                'title' => 'AI-Based Crop Disease Detection Using Convolutional Neural Networks',
                'primary_author_id' => $ccs1->id,
                'mother_college_id' => $ccs->id,
                'registration_type' => 'new',
                'research_classification' => 'internally_funded',
                'agenda_themes' => ['theme_2', 'theme_3'],
                'status' => ResearchStatus::INITIAL_DEAN_REVIEW,
                'sdg_tags' => [2, 3, 9],
                'submitted_at' => '2025-02-14 10:30:00',
                'created_at' => '2025-02-14 10:30:00',
            ],
            [
                'reference_number' => 'AUF-2025-CCS-0004',
                'title' => 'Augmented Reality Application for Anatomy Education',
                'primary_author_id' => $ccs2->id,
                'mother_college_id' => $ccs->id,
                'registration_type' => 'new',
                'research_classification' => 'internally_funded',
                'agenda_themes' => ['theme_2', 'theme_4'],
                'status' => ResearchStatus::INITIAL_DEAN_REVIEW,
                'sdg_tags' => [3, 4, 9],
                'submitted_at' => '2025-07-15 10:00:00',
                'created_at' => '2025-07-15 10:00:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0002',
                'title' => 'Telerehabilitation Outcomes Among Outpatients in Pampanga',
                'primary_author_id' => $camp1->id,
                'mother_college_id' => $camp->id,
                'registration_type' => 'new',
                'research_classification' => 'internally_funded',
                'agenda_themes' => ['theme_1', 'theme_2'],
                'status' => ResearchStatus::INITIAL_OVPRI_REVIEW,
                'sdg_tags' => [3, 9],
                'submitted_at' => '2025-05-18 11:00:00',
                'created_at' => '2025-05-18 11:00:00',
            ],

            // ── Registered / ongoing (in-progress totals, not yet accepted) ──
            [
                'reference_number' => 'AUF-2025-CCS-0007',
                'title' => 'Mobile Learning Analytics for Undergraduate Programming Courses',
                'primary_author_id' => $ccs1->id,
                'mother_college_id' => $ccs->id,
                'registration_type' => 'new',
                'research_classification' => 'college_unit_department_initiated',
                'agenda_themes' => ['theme_4'],
                'status' => ResearchStatus::ONGOING,
                'sdg_tags' => [4, 9],
                'submitted_at' => '2025-01-10 09:00:00',
                'research_registered_at' => '2025-01-20 14:00:00',
                'created_at' => '2025-01-10 09:00:00',
            ],

            // ── Final review queue (completion submitted, outcomes attached) ─
            [
                'reference_number' => 'AUF-2025-CCS-0005',
                'title' => 'Federated Learning Framework for Privacy-Preserving Medical Diagnosis',
                'primary_author_id' => $ccs2->id,
                'mother_college_id' => $ccs->id,
                'registration_type' => 'new',
                'research_classification' => 'externally_funded',
                'funding_agency' => 'DOST',
                'agenda_themes' => ['theme_1', 'theme_2'],
                'status' => ResearchStatus::FINAL_OVPRI_REVIEW,
                'outcomes' => ['completed_not_presented_submitted'],
                'sdg_tags' => [3, 9, 17],
                'submitted_at' => '2025-06-01 09:00:00',
                'research_registered_at' => '2025-06-15 11:00:00',
                'first_completed_at' => '2025-06-25 16:20:00',
                'final_review_count' => 1,
                'created_at' => '2025-06-11 16:20:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0004',
                'title' => 'Laboratory Quality Indicators in Undergraduate Medical Technology Training',
                'primary_author_id' => $camp2->id,
                'mother_college_id' => $camp->id,
                'registration_type' => 'new',
                'research_classification' => 'internally_funded',
                'agenda_themes' => ['theme_1', 'theme_4'],
                'status' => ResearchStatus::FINAL_DEAN_REVIEW,
                'outcomes' => ['completed_not_presented_submitted'],
                'sdg_tags' => [3, 4],
                'submitted_at' => '2025-07-20 09:00:00',
                'research_registered_at' => '2025-07-28 11:00:00',
                'first_completed_at' => '2025-08-01 13:15:00',
                'final_review_count' => 1,
                'created_at' => '2025-08-04 13:15:00',
            ],

            // ── Research accepted (institutional totals + dashboard charts) ──
            [
                'reference_number' => 'AUF-2025-CCS-0003',
                'title' => 'Blockchain-Based Academic Credential Verification System',
                'primary_author_id' => $ccs1->id,
                'mother_college_id' => $ccs->id,
                'registration_type' => 'new',
                'research_classification' => 'self_funded',
                'agenda_themes' => ['theme_2', 'theme_4'],
                'status' => ResearchStatus::RESEARCH_ACCEPTED,
                'outcomes' => ['published_scopus_isi'],
                'sdg_tags' => [4, 9, 17],
                'submitted_at' => '2025-03-02 09:15:00',
                'research_registered_at' => '2025-03-28 14:30:00',
                'first_completed_at' => '2025-04-01 10:00:00',
                'research_accepted_at' => '2025-04-12 14:30:00',
                'final_review_count' => 1,
                'created_at' => '2025-03-02 09:15:00',
            ],
            [
                'reference_number' => 'AUF-2025-CCS-0006',
                'title' => 'IoT-Enabled Smart Campus Energy Management Platform',
                'primary_author_id' => $ccs2->id,
                'mother_college_id' => $ccs->id,
                'registration_type' => 'existing',
                'research_classification' => 'externally_funded',
                'funding_agency' => 'DOST',
                'agenda_themes' => ['theme_2', 'theme_3'],
                'status' => ResearchStatus::RESEARCH_ACCEPTED,
                'outcomes' => ['published_non_scopus_wos'],
                'sdg_tags' => [7, 9, 11],
                'submitted_at' => '2025-04-10 09:00:00',
                'research_registered_at' => '2025-05-15 11:00:00',
                'first_completed_at' => '2025-05-18 10:00:00',
                'research_accepted_at' => '2025-05-28 11:00:00',
                'final_review_count' => 1,
                'created_at' => '2025-04-20 14:00:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0003',
                'title' => 'Ergonomic Interventions for Allied Health Students During Clinical Internships',
                'primary_author_id' => $camp1->id,
                'mother_college_id' => $camp->id,
                'registration_type' => 'existing',
                'research_classification' => 'self_funded',
                'agenda_themes' => ['theme_1', 'theme_5'],
                'status' => ResearchStatus::RESEARCH_ACCEPTED,
                'outcomes' => ['presented_conference_outside_auf'],
                'sdg_tags' => [3, 4, 8],
                'submitted_at' => '2025-01-15 09:00:00',
                'research_registered_at' => '2025-02-20 14:00:00',
                'first_completed_at' => '2025-02-22 09:00:00',
                'research_accepted_at' => '2025-03-01 14:00:00',
                'final_review_count' => 1,
                'created_at' => '2025-01-22 09:00:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0005',
                'title' => 'Antimicrobial Stewardship Practices in Community Pharmacies in Angeles City',
                'primary_author_id' => $camp2->id,
                'mother_college_id' => $camp->id,
                'registration_type' => 'new',
                'research_classification' => 'externally_funded',
                'funding_agency' => 'DOH',
                'agenda_themes' => ['theme_1', 'theme_5'],
                'status' => ResearchStatus::RESEARCH_ACCEPTED,
                'outcomes' => ['published_scopus_isi'],
                'sdg_tags' => [3, 17],
                'submitted_at' => '2025-03-10 09:00:00',
                'research_registered_at' => '2025-04-22 11:15:00',
                'first_completed_at' => '2025-04-25 10:00:00',
                'research_accepted_at' => '2025-05-05 11:15:00',
                'final_review_count' => 1,
                'created_at' => '2025-03-28 10:45:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0006',
                'title' => 'Point-of-Care Testing Competency Among Medical Technology Interns',
                'primary_author_id' => $camp2->id,
                'mother_college_id' => $camp->id,
                'registration_type' => 'new',
                'research_classification' => 'self_funded',
                'agenda_themes' => ['theme_1', 'theme_6'],
                'status' => ResearchStatus::RESEARCH_ACCEPTED,
                'outcomes' => ['presented_conference_auf'],
                'sdg_tags' => [3, 4],
                'submitted_at' => '2025-02-05 09:00:00',
                'research_registered_at' => '2025-03-12 14:00:00',
                'first_completed_at' => '2025-03-15 10:00:00',
                'research_accepted_at' => '2025-03-25 14:00:00',
                'final_review_count' => 1,
                'created_at' => '2025-02-10 14:20:00',
            ],
        ];

        foreach ($records as $data) {
            $this->seedResearchRecord($data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedResearchRecord(array $data): void
    {
        $createdAt = Carbon::parse($data['created_at']);
        $outcomes = $data['outcomes'] ?? [];
        $status = $data['status'];

        $submittedAtRaw = $data['submitted_at'] ?? null;
        $registeredAtRaw = $data['research_registered_at'] ?? null;
        $firstCompletedAtRaw = $data['first_completed_at'] ?? null;
        $acceptedAtRaw = $data['research_accepted_at'] ?? null;

        unset(
            $data['created_at'],
            $data['outcomes'],
            $data['submitted_at'],
            $data['research_registered_at'],
            $data['first_completed_at'],
            $data['research_accepted_at'],
        );

        $author = User::query()->findOrFail($data['primary_author_id']);

        $submittedAt = $submittedAtRaw !== null
            ? Carbon::parse($submittedAtRaw)
            : ($status === ResearchStatus::PROPOSAL ? null : $createdAt->copy()->addHours(1));

        $registeredAt = $registeredAtRaw !== null
            ? Carbon::parse($registeredAtRaw)
            : ($this->hasInitialRegistration($status) ? $submittedAt?->copy()->addDays(5) : null);

        $firstCompletedAt = $firstCompletedAtRaw !== null
            ? Carbon::parse($firstCompletedAtRaw)
            : (($outcomes !== [] && $status !== ResearchStatus::PROPOSAL)
                ? ($registeredAt ?? $createdAt)->copy()->addWeeks(2)
                : null);

        $acceptedAt = $acceptedAtRaw !== null
            ? Carbon::parse($acceptedAtRaw)
            : ($status === ResearchStatus::RESEARCH_ACCEPTED
                ? ($firstCompletedAt ?? $createdAt)->copy()->addDays(7)
                : null);

        $research = Research::updateOrCreate(
            ['reference_number' => $data['reference_number']],
            array_merge($data, [
                'expected_output' => ['publication'],
                'registration_type' => $data['registration_type'] ?? 'new',
                'start_date' => $createdAt->copy()->startOfMonth()->toDateString(),
                'estimated_completion_date' => $createdAt->copy()->addYear()->endOfMonth()->toDateString(),
                'revision_count' => 0,
                'final_review_count' => $data['final_review_count'] ?? ($acceptedAt ? 1 : 0),
                'submitted_at' => $submittedAt,
                'research_registered_at' => $registeredAt,
                'first_completed_at' => $firstCompletedAt,
                'research_accepted_at' => $acceptedAt,
                'is_scopus_indexed' => in_array('published_scopus_isi', $outcomes, true),
            ])
        );

        if ($outcomes !== []) {
            $ids = OutcomeClassification::query()->whereIn('code', $outcomes)->pluck('id');
            $research->outcomeClassifications()->sync($ids);
        } else {
            $research->outcomeClassifications()->sync([]);
        }

        $research->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $acceptedAt ?? $firstCompletedAt ?? $submittedAt ?? $createdAt->copy()->addHours(2),
        ])->saveQuietly();

        ResearchAuthor::query()->updateOrCreate(
            [
                'research_id' => $research->id,
                'user_id' => $author->id,
                'is_primary' => true,
            ],
            [
                'author_type' => 'employee',
                'employee_number' => $author->employee_number,
                'first_name' => $author->first_name,
                'last_name' => $author->last_name,
                'name' => $author->name,
                'college_id' => $author->college_id,
                'program_id' => $author->program_id,
                'email' => $author->email,
                'can_edit' => true,
            ]
        );
    }

    private function hasInitialRegistration(string $status): bool
    {
        return in_array($status, [
            ResearchStatus::RESEARCH_REGISTERED,
            ResearchStatus::ONGOING,
            ResearchStatus::RESEARCH_COMPLETED,
            ResearchStatus::FINAL_DEAN_REVIEW,
            ResearchStatus::FINAL_OVPRI_REVIEW,
            ResearchStatus::FINAL_REJECTED,
            ResearchStatus::RESEARCH_ACCEPTED,
        ], true);
    }
}
