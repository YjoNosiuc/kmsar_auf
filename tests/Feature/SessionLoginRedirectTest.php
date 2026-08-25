<?php

use App\Models\User;

describe('Login redirect and session keep-alive', function () {

    it('does not send faculty back to a dean url stored before login', function () {
        $faculty = makeFaculty(makeCollege());

        $this->get(route('dean.dashboard'))
            ->assertRedirect(route('login'));

        $this->post('/login', [
            'login' => $faculty->email,
            'password' => 'password',
        ])->assertRedirect(route('research.index'));
    });

    it('still returns faculty to an allowed intended research url', function () {
        $faculty = makeFaculty(makeCollege());

        $this->get(route('research.create'))
            ->assertRedirect(route('login'));

        $this->post('/login', [
            'login' => $faculty->email,
            'password' => 'password',
        ])->assertRedirect(route('research.create'));
    });

    it('returns a dean to reports when that was the intended url', function () {
        $college = makeCollege();
        /** @var User $dean */
        $dean = $college->headUser;

        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));

        $this->post('/login', [
            'login' => $dean->email,
            'password' => 'password',
        ])->assertRedirect(route('reports.index'));
    });

    it('keeps an authenticated session alive on ping', function () {
        $faculty = makeFaculty(makeCollege());

        $this->actingAs($faculty)
            ->postJson(route('session.ping'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'csrf']);
    });

    it('serves a fresh csrf token on the login page and csrf endpoint', function () {
        $loginPage = $this->get(route('login'))->assertOk();

        preg_match('/name="_token" value="([^"]+)"/', $loginPage->getContent(), $matches);
        expect($matches[1] ?? '')->not->toBe('');

        $csrfResponse = $this->getJson(route('login.csrf'))
            ->assertOk()
            ->assertJsonStructure(['csrf']);

        expect($csrfResponse->json('csrf'))->not->toBe('');
    });

    it('accepts login when csrf token matches the current session', function () {
        $faculty = makeFaculty(makeCollege());

        $loginPage = $this->get(route('login'))->assertOk();
        preg_match('/name="_token" value="([^"]+)"/', $loginPage->getContent(), $matches);
        $token = $matches[1] ?? '';

        $this->post('/login', [
            '_token' => $token,
            'login' => $faculty->email,
            'password' => 'password',
        ])->assertRedirect(route('research.index'));
    });
});
