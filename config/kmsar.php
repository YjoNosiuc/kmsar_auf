<?php

use App\Support\ResearchStatus;

return [
    'registration_types' => [
        'new',
        'existing',
    ],

    'statuses' => [
        ResearchStatus::DRAFT,
        ResearchStatus::INITIAL_DEAN_REVIEW,
        ResearchStatus::INITIAL_OVPRI_REVIEW,
        ResearchStatus::INITIAL_REJECTED,
        ResearchStatus::RESEARCH_REGISTERED,
        ResearchStatus::RESEARCH_COMPLETED,
        ResearchStatus::FINAL_DEAN_REVIEW,
        ResearchStatus::FINAL_OVPRI_REVIEW,
        ResearchStatus::FINAL_REJECTED,
        ResearchStatus::RESEARCH_ACCEPTED,
    ],

    'initial_review_statuses' => [
        ResearchStatus::INITIAL_DEAN_REVIEW,
        ResearchStatus::INITIAL_OVPRI_REVIEW,
        ResearchStatus::INITIAL_REJECTED,
    ],

    'final_review_statuses' => [
        ResearchStatus::RESEARCH_COMPLETED,
        ResearchStatus::FINAL_DEAN_REVIEW,
        ResearchStatus::FINAL_OVPRI_REVIEW,
        ResearchStatus::FINAL_REJECTED,
    ],

    /*
    | Research classification (registration wizard — single choice).
    */
    'research_classifications' => [
        'student_thesis_dissertation' => 'Student Thesis/Dissertation (with faculty as adviser)',
        'faculty_staff_thesis_dissertation' => 'Faculty/Staff Thesis/Dissertation (with AUF as one of the institutional affiliations)',
        'self_funded' => 'Self-funded (Personal Research)',
        'college_unit_department_initiated' => 'College/Unit/Department Initiated Research',
        'internally_funded' => 'Internally Funded Research (AUF)',
        'externally_funded' => 'Externally Funded Research',
        'creative_work' => 'Creative Work',
        'other' => 'Other',
    ],

    /*
    | AUF Research Agenda Theme (registration wizard — multi select).
    */
    'agenda_themes' => [
        'theme_1' => 'Theme 1: Sustainable Health and Inclusive Well-Being',
        'theme_2' => 'Theme 2: Digital Transformation, AI, and Smart Technologies',
        'theme_3' => 'Theme 3: Environmental Sustainability and Climate Action',
        'theme_4' => 'Theme 4: Equitable and Transformative Education',
        'theme_5' => 'Theme 5: Socioeconomic Innovation, Governance, and Public Policy',
        'theme_6' => 'Theme 6: Cultural Identity, Values Formation, and the Creative Industry',
    ],

    'outcome_classification_codes' => [
        'completed_not_presented_submitted',
        'presented_conference_auf',
        'presented_conference_outside_auf',
        'published_non_scopus_wos',
        'submitted_scopus_isi',
        'accepted_scopus_isi',
        'submitted_patent_ipophl',
        'published_scopus_isi',
        'granted_patent_ipophl',
    ],

    'in_progress_statuses' => [],

    'completed_statuses' => [
        'completed_not_presented_submitted',
        'presented_conference_auf',
        'presented_conference_outside_auf',
        'published_non_scopus_wos',
        'submitted_scopus_isi',
        'accepted_scopus_isi',
        'submitted_patent_ipophl',
        'published_scopus_isi',
        'granted_patent_ipophl',
    ],

    'published_outcome_codes' => [
        'published_non_scopus_wos',
        'published_scopus_isi',
    ],

    'presented_outcome_codes' => [
        'presented_conference_auf',
        'presented_conference_outside_auf',
    ],

    'scopus_outcome_code' => 'published_scopus_isi',

    'max_research_upload_files' => 10,

    'max_research_external_links' => 10,

    'disallowed_external_link_hosts' => [
        'youtube.com',
        'youtu.be',
        'facebook.com',
        'instagram.com',
        'twitter.com',
        'x.com',
        'tiktok.com',
    ],

    'idle_timeout_minutes' => max(1, (int) env('KMSAR_IDLE_TIMEOUT_MINUTES', 2)),
    'idle_countdown_seconds' => max(10, (int) env('KMSAR_IDLE_COUNTDOWN_SECONDS', 30)),
];
