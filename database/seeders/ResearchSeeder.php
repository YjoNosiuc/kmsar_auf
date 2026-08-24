<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
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
            // CCS 1 — Maria Santos
            [
                'reference_number' => 'AUF-2025-CCS-0001',
                'title' => 'Natural Language Processing for Tagalog Sentiment Analysis',
                'primary_author_id' => $ccs1->id,
                'mother_college_id' => $ccs->id,
                'research_classification' => 'thesis',
                'funding_agency' => null,
                'status' => 'proposal',
                'approval_stage' => 'draft',
                'sdg_tags' => [4, 9],
                'created_at' => '2025-09-08 11:45:00',
            ],
            [
                'reference_number' => 'AUF-2025-CCS-0002',
                'title' => 'AI-Based Crop Disease Detection Using Convolutional Neural Networks',
                'primary_author_id' => $ccs1->id,
                'mother_college_id' => $ccs->id,
                'research_classification' => 'internally_funded',
                'funding_agency' => null,
                'status' => 'ongoing',
                'approval_stage' => 'dean_review',
                'sdg_tags' => [2, 3, 9],
                'created_at' => '2025-02-14 10:30:00',
            ],
            [
                'reference_number' => 'AUF-2025-CCS-0003',
                'title' => 'Blockchain-Based Academic Credential Verification System',
                'primary_author_id' => $ccs1->id,
                'mother_college_id' => $ccs->id,
                'research_classification' => 'self_funded',
                'funding_agency' => null,
                'status' => 'published_scopus',
                'approval_stage' => 'approved',
                'sdg_tags' => [4, 9, 17],
                'is_scopus_indexed' => true,
                'created_at' => '2025-03-02 09:15:00',
            ],

            // CCS 2 — Juan Dela Cruz
            [
                'reference_number' => 'AUF-2025-CCS-0004',
                'title' => 'Augmented Reality Application for Anatomy Education',
                'primary_author_id' => $ccs2->id,
                'mother_college_id' => $ccs->id,
                'research_classification' => 'internally_funded',
                'funding_agency' => null,
                'status' => 'proposal',
                'approval_stage' => 'dean_review',
                'sdg_tags' => [3, 4, 9],
                'created_at' => '2025-07-15 10:00:00',
            ],
            [
                'reference_number' => 'AUF-2025-CCS-0005',
                'title' => 'Federated Learning Framework for Privacy-Preserving Medical Diagnosis',
                'primary_author_id' => $ccs2->id,
                'mother_college_id' => $ccs->id,
                'research_classification' => 'externally_funded',
                'funding_agency' => 'DOST',
                'status' => 'completed_unpublished',
                'approval_stage' => 'ovpri_review',
                'sdg_tags' => [3, 9, 17],
                'created_at' => '2025-06-11 16:20:00',
            ],
            [
                'reference_number' => 'AUF-2025-CCS-0006',
                'title' => 'IoT-Enabled Smart Campus Energy Management Platform',
                'primary_author_id' => $ccs2->id,
                'mother_college_id' => $ccs->id,
                'research_classification' => 'externally_funded',
                'funding_agency' => 'DOST',
                'status' => 'published_non_indexed',
                'approval_stage' => 'approved',
                'sdg_tags' => [7, 9, 11],
                'created_at' => '2025-04-20 14:00:00',
            ],

            // CAMP 1 — Elena Cruz
            [
                'reference_number' => 'AUF-2025-CAMP-0001',
                'title' => 'Home-Based Physical Therapy Protocol for Post-Stroke Recovery',
                'primary_author_id' => $camp1->id,
                'mother_college_id' => $camp->id,
                'research_classification' => 'thesis',
                'funding_agency' => null,
                'status' => 'proposal',
                'approval_stage' => 'draft',
                'sdg_tags' => [3, 10],
                'created_at' => '2025-09-12 08:30:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0002',
                'title' => 'Telerehabilitation Outcomes Among Outpatients in Pampanga',
                'primary_author_id' => $camp1->id,
                'mother_college_id' => $camp->id,
                'research_classification' => 'internally_funded',
                'funding_agency' => null,
                'status' => 'ongoing',
                'approval_stage' => 'ovpri_review',
                'sdg_tags' => [3, 9],
                'created_at' => '2025-05-18 11:00:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0003',
                'title' => 'Ergonomic Interventions for Allied Health Students During Clinical Internships',
                'primary_author_id' => $camp1->id,
                'mother_college_id' => $camp->id,
                'research_classification' => 'self_funded',
                'funding_agency' => null,
                'status' => 'presented_external',
                'approval_stage' => 'approved',
                'sdg_tags' => [3, 4, 8],
                'created_at' => '2025-01-22 09:00:00',
            ],

            // CAMP 2 — Paolo Reyes
            [
                'reference_number' => 'AUF-2025-CAMP-0004',
                'title' => 'Laboratory Quality Indicators in Undergraduate Medical Technology Training',
                'primary_author_id' => $camp2->id,
                'mother_college_id' => $camp->id,
                'research_classification' => 'internally_funded',
                'funding_agency' => null,
                'status' => 'completed_unpublished',
                'approval_stage' => 'dean_review',
                'sdg_tags' => [3, 4],
                'created_at' => '2025-08-04 13:15:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0005',
                'title' => 'Antimicrobial Stewardship Practices in Community Pharmacies in Angeles City',
                'primary_author_id' => $camp2->id,
                'mother_college_id' => $camp->id,
                'research_classification' => 'externally_funded',
                'funding_agency' => 'DOH',
                'status' => 'published_scopus',
                'approval_stage' => 'approved',
                'sdg_tags' => [3, 17],
                'is_scopus_indexed' => true,
                'created_at' => '2025-03-28 10:45:00',
            ],
            [
                'reference_number' => 'AUF-2025-CAMP-0006',
                'title' => 'Point-of-Care Testing Competency Among Medical Technology Interns',
                'primary_author_id' => $camp2->id,
                'mother_college_id' => $camp->id,
                'research_classification' => 'self_funded',
                'funding_agency' => null,
                'status' => 'presented_internal',
                'approval_stage' => 'approved',
                'sdg_tags' => [3, 4],
                'created_at' => '2025-02-10 14:20:00',
            ],
        ];

        foreach ($records as $data) {
            $createdAt = Carbon::parse($data['created_at']);
            unset($data['created_at']);

            $author = User::query()->findOrFail($data['primary_author_id']);
            $stage = $data['approval_stage'];

            $research = Research::updateOrCreate(
                ['reference_number' => $data['reference_number']],
                array_merge($data, [
                    'expected_output' => ['publication'],
                    'is_scopus_indexed' => $data['is_scopus_indexed'] ?? false,
                    'registration_type' => 'new',
                    'start_date' => $createdAt->copy()->startOfMonth(),
                    'estimated_completion_date' => $createdAt->copy()->addYear()->endOfMonth(),
                    'revision_count' => 0,
                    'submitted_at' => $stage === 'draft' ? null : $createdAt->copy()->addHours(1),
                ])
            );

            $research->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHours(2),
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
    }
}
