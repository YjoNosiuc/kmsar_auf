<?php

namespace Database\Seeders;

use App\Models\OutcomeClassification;
use Illuminate\Database\Seeder;

class OutcomeClassificationSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'completed_not_presented_submitted', 'name' => 'Completed Research but NOT Presented/Submitted', 'sort_order' => 1],
            ['code' => 'presented_conference_auf', 'name' => 'Presented in a conference inside AUF', 'sort_order' => 2],
            ['code' => 'presented_conference_outside_auf', 'name' => 'Presented in a conference outside AUF (local/international)', 'sort_order' => 3],
            ['code' => 'published_non_scopus_wos', 'name' => 'Published in non-scopus/WoS indexed journals', 'sort_order' => 4],
            ['code' => 'submitted_scopus_isi', 'name' => 'Submitted in Scopus or ISI (Web of Science) indexed journals', 'sort_order' => 5],
            ['code' => 'accepted_scopus_isi', 'name' => 'Accepted in Scopus or ISI (Web of Science) indexed journals', 'sort_order' => 6],
            ['code' => 'submitted_patent_ipophl', 'name' => 'Submitted for patent application at IPOPHL', 'sort_order' => 7],
            ['code' => 'published_scopus_isi', 'name' => 'Published in Scopus or ISI (Web of Science) indexed journals', 'sort_order' => 8],
            ['code' => 'granted_patent_ipophl', 'name' => 'Granted Philippine Patent by IPOPHL', 'sort_order' => 9],
        ];

        $activeCodes = collect($rows)->pluck('code')->all();

        OutcomeClassification::query()
            ->whereNotIn('code', $activeCodes)
            ->update(['is_active' => false]);

        foreach ($rows as $row) {
            OutcomeClassification::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
