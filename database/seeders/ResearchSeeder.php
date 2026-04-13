<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Research;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ResearchSeeder extends Seeder
{
    public function run(): void
    {
        $ccs = College::where('code', 'CCS')->firstOrFail();
        $cba = College::where('code', 'CBA')->firstOrFail();
        $cea = College::where('code', 'CEA')->firstOrFail();

        // CCS Faculty
        $ccs1 = User::where('email', 'faculty.ccs1@auf.edu.ph')->firstOrFail();
        $ccs2 = User::where('email', 'faculty.ccs2@auf.edu.ph')->firstOrFail();
        $ccs3 = User::where('email', 'faculty.ccs3@auf.edu.ph')->firstOrFail();

        // CBA Faculty
        $cba1 = User::where('email', 'faculty.cba1@auf.edu.ph')->firstOrFail();
        $cba2 = User::where('email', 'faculty.cba2@auf.edu.ph')->firstOrFail();
        $cba3 = User::where('email', 'faculty.cba3@auf.edu.ph')->firstOrFail();

        // CEA Faculty
        $cea1 = User::where('email', 'faculty.cea1@auf.edu.ph')->firstOrFail();
        $cea2 = User::where('email', 'faculty.cea2@auf.edu.ph')->firstOrFail();
        $cea3 = User::where('email', 'faculty.cea3@auf.edu.ph')->firstOrFail();

        $records = [

            // ══════════════════════════════════════════
            // CCS — College of Computer Studies
            // ══════════════════════════════════════════

            [
                'reference_number'         => 'AUF-2024-CCS-0001',
                'title'                    => 'AI-Based Crop Disease Detection Using Convolutional Neural Networks',
                'primary_author_id'        => $ccs1->id,
                'mother_college_id'        => $ccs->id,
                'research_classification'  => 'internally_funded',
                'funding_agency'           => null,
                'status'                   => 'ongoing',
                'approval_stage'           => 'dean_review',
                'sdg_tags'                 => [2, 3, 9],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-02-14 10:30:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CCS-0002',
                'title'                    => 'Blockchain-Based Academic Credential Verification System',
                'primary_author_id'        => $ccs1->id,
                'mother_college_id'        => $ccs->id,
                'research_classification'  => 'self_funded',
                'funding_agency'           => null,
                'status'                   => 'published_non_indexed',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [4, 9, 17],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-03-02 09:15:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CCS-0003',
                'title'                    => 'IoT-Enabled Smart Campus Energy Management Platform',
                'primary_author_id'        => $ccs2->id,
                'mother_college_id'        => $ccs->id,
                'research_classification'  => 'externally_funded',
                'funding_agency'           => 'DOST',
                'status'                   => 'published_scopus',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [7, 9, 11],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => true,
                'registration_type'        => 'new',
                'created_at'               => '2024-04-20 14:00:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CCS-0004',
                'title'                    => 'Natural Language Processing for Tagalog Sentiment Analysis',
                'primary_author_id'        => $ccs1->id,
                'mother_college_id'        => $ccs->id,
                'research_classification'  => 'thesis',
                'funding_agency'           => null,
                'status'                   => 'proposal',
                'approval_stage'           => 'draft',
                'sdg_tags'                 => [4, 9],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-05-08 11:45:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CCS-0005',
                'title'                    => 'Federated Learning Framework for Privacy-Preserving Medical Diagnosis',
                'primary_author_id'        => $ccs2->id,
                'mother_college_id'        => $ccs->id,
                'research_classification'  => 'internally_funded',
                'funding_agency'           => null,
                'status'                   => 'published_scopus',
                'approval_stage'           => 'ovpri_review',
                'sdg_tags'                 => [3, 9, 17],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => true,
                'registration_type'        => 'new',
                'created_at'               => '2024-06-11 16:20:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CCS-0006',
                'title'                    => 'Augmented Reality Application for Anatomy Education in Medical Schools',
                'primary_author_id'        => $ccs3->id,
                'mother_college_id'        => $ccs->id,
                'research_classification'  => 'externally_funded',
                'funding_agency'           => 'CHED',
                'status'                   => 'presented_external',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [3, 4, 9],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-07-15 10:00:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CCS-0007',
                'title'                    => 'Deep Learning-Based Pothole Detection System for Smart Roads',
                'primary_author_id'        => $ccs3->id,
                'mother_college_id'        => $ccs->id,
                'research_classification'  => 'self_funded',
                'funding_agency'           => null,
                'status'                   => 'completed_unpublished',
                'approval_stage'           => 'dean_review',
                'sdg_tags'                 => [9, 11],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-08-22 13:30:00',
            ],
            [
                'reference_number'         => 'AUF-2023-CCS-0001',
                'title'                    => 'Machine Learning Model for Student Dropout Prediction in Philippine HEIs',
                'primary_author_id'        => $ccs2->id,
                'mother_college_id'        => $ccs->id,
                'research_classification'  => 'internally_funded',
                'funding_agency'           => null,
                'status'                   => 'published_scopus',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [4, 9, 17],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => true,
                'registration_type'        => 'new',
                'created_at'               => '2023-03-10 09:00:00',
            ],
            [
                'reference_number'         => 'AUF-2023-CCS-0002',
                'title'                    => 'Mobile-Based Telemedicine Platform for Remote Barangay Healthcare',
                'primary_author_id'        => $ccs1->id,
                'mother_college_id'        => $ccs->id,
                'research_classification'  => 'externally_funded',
                'funding_agency'           => 'DOH',
                'status'                   => 'presented_internal',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [3, 9, 10],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2023-05-18 14:00:00',
            ],

            // ══════════════════════════════════════════
            // CBA — College of Business and Accountancy
            // ══════════════════════════════════════════

            [
                'reference_number'         => 'AUF-2024-CBA-0001',
                'title'                    => 'Digital Transformation of MSMEs in Post-Pandemic Pampanga',
                'primary_author_id'        => $cba1->id,
                'mother_college_id'        => $cba->id,
                'research_classification'  => 'self_funded',
                'funding_agency'           => null,
                'status'                   => 'published_non_indexed',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [8, 9, 17],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-01-20 08:30:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CBA-0002',
                'title'                    => 'Financial Literacy and Investment Behavior of AUF College Students',
                'primary_author_id'        => $cba2->id,
                'mother_college_id'        => $cba->id,
                'research_classification'  => 'internally_funded',
                'funding_agency'           => null,
                'status'                   => 'ongoing',
                'approval_stage'           => 'dean_review',
                'sdg_tags'                 => [1, 4, 10],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-02-28 11:00:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CBA-0003',
                'title'                    => 'Impact of Sustainable Tourism Practices on Local Economic Growth in Central Luzon',
                'primary_author_id'        => $cba3->id,
                'mother_college_id'        => $cba->id,
                'research_classification'  => 'externally_funded',
                'funding_agency'           => 'DOT',
                'status'                   => 'presented_external',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [8, 11, 12, 17],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-03-15 15:30:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CBA-0004',
                'title'                    => 'Corporate Social Responsibility and Firm Performance Among Philippine Banks',
                'primary_author_id'        => $cba1->id,
                'mother_college_id'        => $cba->id,
                'research_classification'  => 'self_funded',
                'funding_agency'           => null,
                'status'                   => 'published_scopus',
                'approval_stage'           => 'ovpri_review',
                'sdg_tags'                 => [8, 10, 17],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => true,
                'registration_type'        => 'new',
                'created_at'               => '2024-04-10 09:00:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CBA-0005',
                'title'                    => 'E-Commerce Adoption Among SMEs in Angeles City During COVID-19 Recovery',
                'primary_author_id'        => $cba2->id,
                'mother_college_id'        => $cba->id,
                'research_classification'  => 'thesis',
                'funding_agency'           => null,
                'status'                   => 'proposal',
                'approval_stage'           => 'draft',
                'sdg_tags'                 => [8, 9],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-09-05 10:00:00',
            ],
            [
                'reference_number'         => 'AUF-2023-CBA-0001',
                'title'                    => 'Working Capital Management and Profitability of Listed Manufacturing Firms in the Philippines',
                'primary_author_id'        => $cba3->id,
                'mother_college_id'        => $cba->id,
                'research_classification'  => 'internally_funded',
                'funding_agency'           => null,
                'status'                   => 'published_non_indexed',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [8, 9],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2023-06-12 14:00:00',
            ],

            // ══════════════════════════════════════════
            // CEA — College of Engineering
            // ══════════════════════════════════════════

            [
                'reference_number'         => 'AUF-2024-CEA-0001',
                'title'                    => 'Seismic Performance Analysis of Pre-1990 Reinforced Concrete Buildings in Pampanga',
                'primary_author_id'        => $cea1->id,
                'mother_college_id'        => $cea->id,
                'research_classification'  => 'externally_funded',
                'funding_agency'           => 'DOST',
                'status'                   => 'presented_external',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [9, 11],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-02-05 09:00:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CEA-0002',
                'title'                    => 'Design and Development of a Low-Cost Solar-Powered Water Purification System',
                'primary_author_id'        => $cea2->id,
                'mother_college_id'        => $cea->id,
                'research_classification'  => 'externally_funded',
                'funding_agency'           => 'DOE',
                'status'                   => 'published_scopus',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [6, 7, 9],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => true,
                'registration_type'        => 'new',
                'created_at'               => '2024-03-18 11:30:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CEA-0003',
                'title'                    => 'Traffic Flow Optimization Using Adaptive Signal Control in Pampanga Urban Roads',
                'primary_author_id'        => $cea3->id,
                'mother_college_id'        => $cea->id,
                'research_classification'  => 'internally_funded',
                'funding_agency'           => null,
                'status'                   => 'ongoing',
                'approval_stage'           => 'dean_review',
                'sdg_tags'                 => [9, 11, 13],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-05-01 10:00:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CEA-0004',
                'title'                    => 'Structural Assessment of Heritage Buildings in Angeles City for Adaptive Reuse',
                'primary_author_id'        => $cea1->id,
                'mother_college_id'        => $cea->id,
                'research_classification'  => 'self_funded',
                'funding_agency'           => null,
                'status'                   => 'completed_unpublished',
                'approval_stage'           => 'ovpri_review',
                'sdg_tags'                 => [11, 17],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-06-20 14:00:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CEA-0005',
                'title'                    => 'Waste-to-Energy Conversion Using Pyrolysis Technology for Municipal Solid Waste',
                'primary_author_id'        => $cea2->id,
                'mother_college_id'        => $cea->id,
                'research_classification'  => 'externally_funded',
                'funding_agency'           => 'DOST',
                'status'                   => 'presented_internal',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [7, 11, 12, 13],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-08-10 09:30:00',
            ],
            [
                'reference_number'         => 'AUF-2024-CEA-0006',
                'title'                    => 'Development of Bamboo-Reinforced Concrete Composites for Low-Cost Housing',
                'primary_author_id'        => $cea3->id,
                'mother_college_id'        => $cea->id,
                'research_classification'  => 'thesis',
                'funding_agency'           => null,
                'status'                   => 'proposal',
                'approval_stage'           => 'draft',
                'sdg_tags'                 => [9, 11],
                'expected_output'          => ['patent'],
                'is_scopus_indexed'        => false,
                'registration_type'        => 'new',
                'created_at'               => '2024-09-15 08:00:00',
            ],
            [
                'reference_number'         => 'AUF-2023-CEA-0001',
                'title'                    => 'Geotechnical Investigation of Liquefaction Susceptibility in Pampanga Flood Plains',
                'primary_author_id'        => $cea1->id,
                'mother_college_id'        => $cea->id,
                'research_classification'  => 'externally_funded',
                'funding_agency'           => 'PHIVOLCS',
                'status'                   => 'published_scopus',
                'approval_stage'           => 'approved',
                'sdg_tags'                 => [9, 11, 13],
                'expected_output'          => ['publication'],
                'is_scopus_indexed'        => true,
                'registration_type'        => 'new',
                'created_at'               => '2023-04-22 10:00:00',
            ],
        ];

        foreach ($records as $data) {
            $createdAt = Carbon::parse($data['created_at']);
            unset($data['created_at']);

            $payload = array_merge($data, [
                'start_date'                => $createdAt->copy()->startOfMonth(),
                'estimated_completion_date' => $createdAt->copy()->addYear()->endOfMonth(),
                'revision_count'            => 0,
            ]);

            $research = Research::updateOrCreate(
                ['reference_number' => $payload['reference_number']],
                $payload
            );

            $research->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHours(2),
            ])->saveQuietly();
        }
    }
}
