<?php

use App\Models\College;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
});

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────

/**
 * Create a user with the given Spatie role (TestingSeeder provides roles/permissions).
 */
function makeUserWithRole(string $role, array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'is_active' => true,
    ], $attributes));
    $user->assignRole($role);

    return $user;
}

/**
 * User tied to a factory college (dean, unit head, faculty, co-author tests).
 */
function userWithCollege(string $role): User
{
    $college = College::factory()->create(['is_active' => true]);

    return makeUserWithRole($role, ['college_id' => $college->id]);
}

// ─────────────────────────────────────────────
// SUPER ADMIN
// ─────────────────────────────────────────────

describe('Super Admin', function () {

    it('can access /admin/dashboard', function () {
        $user = makeUserWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk();
    });

    it('can access /admin/users', function () {
        $user = makeUserWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk();
    });

    it('can access /admin/colleges', function () {
        $user = makeUserWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('admin.colleges.index'))
            ->assertOk();
    });

    it('can access /admin/audit-logs', function () {
        $user = makeUserWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('audit.index'))
            ->assertOk();
    });

    it('cannot access /research (no faculty role)', function () {
        $user = makeUserWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('research.index'))
            ->assertForbidden();
    });

    it('cannot access /dean/dashboard', function () {
        $user = makeUserWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /ovpri/dashboard', function () {
        $user = makeUserWithRole('super_admin');

        $this->actingAs($user)
            ->get(route('ovpri.dashboard'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// OVPRI ADMIN
// ─────────────────────────────────────────────

describe('OVPRI Admin', function () {

    it('can access /ovpri/dashboard', function () {
        $user = makeUserWithRole('ovpri_admin');

        $this->actingAs($user)
            ->get(route('ovpri.dashboard'))
            ->assertOk();
    });

    it('can access /ovpri/queue', function () {
        $user = makeUserWithRole('ovpri_admin');

        $this->actingAs($user)
            ->get(route('ovpri.queue'))
            ->assertOk();
    });

    it('can access /ovpri/research', function () {
        $user = makeUserWithRole('ovpri_admin');

        $this->actingAs($user)
            ->get(route('ovpri.research'))
            ->assertOk();
    });

    it('can access /reports', function () {
        $user = makeUserWithRole('ovpri_admin');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk();
    });

    it('cannot access /admin/dashboard', function () {
        $user = makeUserWithRole('ovpri_admin');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /dean/dashboard', function () {
        $user = makeUserWithRole('ovpri_admin');

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /research', function () {
        $user = makeUserWithRole('ovpri_admin');

        $this->actingAs($user)
            ->get(route('research.index'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// CDAIC ADMIN (same as OVPRI Admin)
// ─────────────────────────────────────────────

describe('CDAIC Admin', function () {

    it('can access /ovpri/dashboard', function () {
        $user = makeUserWithRole('cdaic_admin');

        $this->actingAs($user)
            ->get(route('ovpri.dashboard'))
            ->assertOk();
    });

    it('can access /ovpri/queue', function () {
        $user = makeUserWithRole('cdaic_admin');

        $this->actingAs($user)
            ->get(route('ovpri.queue'))
            ->assertOk();
    });

    it('can access /ovpri/research', function () {
        $user = makeUserWithRole('cdaic_admin');

        $this->actingAs($user)
            ->get(route('ovpri.research'))
            ->assertOk();
    });

    it('can access /reports', function () {
        $user = makeUserWithRole('cdaic_admin');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk();
    });

    it('cannot access /admin/dashboard', function () {
        $user = makeUserWithRole('cdaic_admin');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /dean/dashboard', function () {
        $user = makeUserWithRole('cdaic_admin');

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /research', function () {
        $user = makeUserWithRole('cdaic_admin');

        $this->actingAs($user)
            ->get(route('research.index'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// COLLEGE DEAN
// ─────────────────────────────────────────────

describe('College Dean', function () {

    it('can access /dean/dashboard', function () {
        $user = userWithCollege('college_dean');

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertOk();
    });

    it('can access /approval/queue', function () {
        $user = userWithCollege('college_dean');

        $this->actingAs($user)
            ->get(route('approval.queue'))
            ->assertOk();
    });

    it('can access /reports', function () {
        $user = userWithCollege('college_dean');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk();
    });

    it('cannot access /admin/dashboard', function () {
        $user = userWithCollege('college_dean');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /ovpri/dashboard', function () {
        $user = userWithCollege('college_dean');

        $this->actingAs($user)
            ->get(route('ovpri.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /research', function () {
        $user = userWithCollege('college_dean');

        $this->actingAs($user)
            ->get(route('research.index'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// UNIT HEAD (same as College Dean)
// ─────────────────────────────────────────────

describe('Unit Head', function () {

    it('can access /dean/dashboard', function () {
        $user = userWithCollege('unit_head');

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertOk();
    });

    it('can access /approval/queue', function () {
        $user = userWithCollege('unit_head');

        $this->actingAs($user)
            ->get(route('approval.queue'))
            ->assertOk();
    });

    it('can access /reports', function () {
        $user = userWithCollege('unit_head');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk();
    });

    it('cannot access /admin/dashboard', function () {
        $user = userWithCollege('unit_head');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /ovpri/dashboard', function () {
        $user = userWithCollege('unit_head');

        $this->actingAs($user)
            ->get(route('ovpri.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /research', function () {
        $user = userWithCollege('unit_head');

        $this->actingAs($user)
            ->get(route('research.index'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// FACULTY
// ─────────────────────────────────────────────

describe('Faculty', function () {

    it('can access /research', function () {
        $user = userWithCollege('faculty');

        $this->actingAs($user)
            ->get(route('research.index'))
            ->assertOk();
    });

    it('can access /research/create', function () {
        $user = userWithCollege('faculty');

        $this->actingAs($user)
            ->get(route('research.create'))
            ->assertRedirect();
    });

    it('cannot access /admin/dashboard', function () {
        $user = userWithCollege('faculty');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /dean/dashboard', function () {
        $user = userWithCollege('faculty');

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /ovpri/dashboard', function () {
        $user = userWithCollege('faculty');

        $this->actingAs($user)
            ->get(route('ovpri.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /approval/queue', function () {
        $user = userWithCollege('faculty');

        $this->actingAs($user)
            ->get(route('approval.queue'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// CO-AUTHOR
// ─────────────────────────────────────────────

describe('Co-Author', function () {

    it('can access /research', function () {
        $user = userWithCollege('co_author');

        $this->actingAs($user)
            ->get(route('research.index'))
            ->assertOk();
    });

    it('can access /research/create', function () {
        $user = userWithCollege('co_author');

        $this->actingAs($user)
            ->get(route('research.create'))
            ->assertRedirect();
    });

    it('cannot access /admin/dashboard', function () {
        $user = userWithCollege('co_author');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /approval/queue', function () {
        $user = userWithCollege('co_author');

        $this->actingAs($user)
            ->get(route('approval.queue'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// REGISTRAR
// ─────────────────────────────────────────────

describe('Registrar', function () {

    it('can access /profile (authenticated baseline)', function () {
        $user = makeUserWithRole('registrar');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    });

    it('cannot access /research (policy denies viewAny even with research.view_all)', function () {
        $user = makeUserWithRole('registrar');

        $this->actingAs($user)
            ->get(route('research.index'))
            ->assertForbidden();
    });

    it('cannot access /admin/dashboard', function () {
        $user = makeUserWithRole('registrar');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('cannot access /dean/dashboard', function () {
        $user = makeUserWithRole('registrar');

        $this->actingAs($user)
            ->get(route('dean.dashboard'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// VIEWER
// ─────────────────────────────────────────────

describe('Viewer', function () {

    it('can access /profile (authenticated baseline)', function () {
        $user = makeUserWithRole('viewer');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    });

    it('cannot access /reports (route middleware blocks viewer role)', function () {
        $user = makeUserWithRole('viewer');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    });

    it('cannot access /research', function () {
        $user = makeUserWithRole('viewer');

        $this->actingAs($user)
            ->get(route('research.index'))
            ->assertForbidden();
    });

    it('cannot access /admin/dashboard', function () {
        $user = makeUserWithRole('viewer');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// GUEST (UNAUTHENTICATED)
// ─────────────────────────────────────────────

describe('Guest (unauthenticated)', function () {

    it('gets redirected to login from /research', function () {
        $this->get(route('research.index'))
            ->assertRedirect(route('login'));
    });

    it('gets redirected to login from /admin/dashboard', function () {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    });

    it('gets redirected to login from /dean/dashboard', function () {
        $this->get(route('dean.dashboard'))
            ->assertRedirect(route('login'));
    });

    it('gets redirected to login from /ovpri/dashboard', function () {
        $this->get(route('ovpri.dashboard'))
            ->assertRedirect(route('login'));
    });
});
