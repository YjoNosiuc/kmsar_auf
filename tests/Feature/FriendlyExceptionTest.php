<?php

use App\Support\ResearchStatus;

describe('Friendly exception responses', function () {

    it('redirects OVPRI double-approve instead of showing 403', function () {
        $college = makeCollege();
        $faculty = makeFaculty($college);
        $ovpri = makeOvpri();
        $research = makeDraftResearch($faculty, $college);
        $research->update(['status' => ResearchStatus::INITIAL_OVPRI_REVIEW]);

        $this->actingAs($ovpri)
            ->post(route('ovpri.approve', $research), ['remarks' => 'Approved.'])
            ->assertRedirect();

        $this->actingAs($ovpri)
            ->post(route('ovpri.approve', $research), ['remarks' => 'Again.'])
            ->assertRedirect(route('ovpri.queue', ['cycle' => 'initial', 'tab' => 'approved']))
            ->assertSessionHas('info');
    });

    it('redirects faculty away from admin routes instead of 403 page', function () {
        $faculty = makeFaculty(makeCollege());

        $this->actingAs($faculty)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('research.index'))
            ->assertSessionHas('error');
    });
});
