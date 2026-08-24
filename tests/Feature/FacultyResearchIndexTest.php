<?php

use App\Models\Research;

describe('Faculty My Research filters', function () {

    it('applies search across all pages, not only the current page', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);

        $hiddenTitle = 'UNIQUE CROSS PAGE FILTER TITLE XYZ';

        Research::factory()->create([
            'title' => $hiddenTitle,
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'research_classification' => 'self_funded',
            'created_at' => now()->subDays(40),
        ]);

        foreach (range(1, 15) as $i) {
            Research::factory()->create([
                'title' => "NEWER LIST ITEM {$i}",
                'primary_author_id' => $faculty->id,
                'mother_college_id' => $college->id,
                'approval_stage' => 'approved',
                'status' => 'ongoing',
                'research_classification' => 'self_funded',
                'created_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($faculty)
            ->get(route('research.index'))
            ->assertOk()
            ->assertDontSee($hiddenTitle)
            ->assertSee('NEWER LIST ITEM 1');

        $this->actingAs($faculty)
            ->get(route('research.index', ['search' => 'CROSS PAGE FILTER']))
            ->assertOk()
            ->assertSee($hiddenTitle)
            ->assertDontSee('NEWER LIST ITEM 1');
    });

    it('filters by approval stage and progress status on the server', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);

        Research::factory()->create([
            'title' => 'DRAFT PROPOSAL RECORD',
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'draft',
            'status' => 'proposal',
            'research_classification' => 'self_funded',
        ]);

        Research::factory()->create([
            'title' => 'APPROVED ONGOING RECORD',
            'primary_author_id' => $faculty->id,
            'mother_college_id' => $college->id,
            'approval_stage' => 'approved',
            'status' => 'ongoing',
            'research_classification' => 'self_funded',
        ]);

        $this->actingAs($faculty)
            ->get(route('research.index', ['approval_stage' => 'draft']))
            ->assertOk()
            ->assertSee('DRAFT PROPOSAL RECORD')
            ->assertDontSee('APPROVED ONGOING RECORD');

        $this->actingAs($faculty)
            ->get(route('research.index', ['status' => 'ongoing']))
            ->assertOk()
            ->assertSee('APPROVED ONGOING RECORD')
            ->assertDontSee('DRAFT PROPOSAL RECORD');
    });

    it('keeps search filters in pagination links', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);

        foreach (range(1, 16) as $i) {
            Research::factory()->create([
                'title' => "SEARCHABLE BLOCKCHAIN STUDY {$i}",
                'primary_author_id' => $faculty->id,
                'mother_college_id' => $college->id,
                'approval_stage' => 'approved',
                'status' => 'ongoing',
                'research_classification' => 'self_funded',
                'created_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($faculty)
            ->get(route('research.index', ['search' => 'BLOCKCHAIN']))
            ->assertOk()
            ->assertSee('search=BLOCKCHAIN');
    });
});
