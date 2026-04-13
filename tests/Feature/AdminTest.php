<?php

/**
 * Verifies super_admin CRUD for users, colleges, programs, and audit logs in KMSAR.
 *
 * Rules:
 * - Only super_admin can access admin routes (enforced by middleware; tests assert 403 for others).
 * - Use User::factory()->create() + assignRole() for all users.
 * - Never hardcode college codes — unique faker values.
 * - Text fields stored uppercase — assert DB values are uppercase.
 */

use App\Models\AuditLog;
use App\Models\College;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Str;

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────

function adminMakeSuperAdmin(): User
{
    $user = User::factory()->create([
        'is_active' => true,
        'employee_number' => strtoupper(Str::random(8)),
        'first_name' => 'SUPER',
        'last_name' => 'ADMIN',
    ]);
    $user->assignRole('super_admin');

    return $user;
}

function adminMakeNonSuperAdmin(string $role): User
{
    $user = User::factory()->create([
        'is_active' => true,
        'employee_number' => strtoupper(Str::random(8)),
        'first_name' => 'OTHER',
        'last_name' => 'ROLE',
    ]);
    $user->assignRole($role);

    return $user;
}

function adminCreateAuditLog(array $overrides = []): AuditLog
{
    return AuditLog::create(array_merge([
        'user_id' => null,
        'action' => 'test_action',
        'auditable_type' => User::class,
        'auditable_id' => 1,
        'old_values' => null,
        'new_values' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'created_at' => now(),
    ], $overrides));
}

// ─────────────────────────────────────────────
// USER MANAGEMENT
// ─────────────────────────────────────────────

describe('Admin user management', function () {

    it('super_admin can view the users list', function () {
        $admin = adminMakeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();
    });

    it('super_admin can create a new user with a role', function () {
        $admin = adminMakeSuperAdmin();

        $employeeNumber = fake()->unique()->bothify('??####');
        $email = fake()->unique()->safeEmail();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'employee_number' => $employeeNumber,
                'first_name' => 'Newbie',
                'last_name' => 'Faculty',
                'middle_name' => 'Middle',
                'suffix' => null,
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'college_id' => null,
                'role' => 'faculty',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', $email)->first();
        expect($created)->not->toBeNull()
            ->and($created->hasRole('faculty'))->toBeTrue();
    });

    it('super_admin can edit an existing user', function () {
        $admin = adminMakeSuperAdmin();
        $target = User::factory()->create([
            'is_active' => true,
            'employee_number' => fake()->unique()->bothify('??####'),
            'first_name' => 'EDIT',
            'last_name' => 'ME',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $target->assignRole('viewer');

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $target->id,
                'email' => $target->email,
            ]);
    });

    it('super_admin can update a user role', function () {
        $admin = adminMakeSuperAdmin();
        $target = User::factory()->create([
            'is_active' => true,
            'employee_number' => fake()->unique()->bothify('??####'),
            'first_name' => 'ROLE',
            'last_name' => 'CHANGE',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $target->assignRole('viewer');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'employee_number' => $target->employee_number,
                'first_name' => $target->first_name,
                'last_name' => $target->last_name,
                'middle_name' => $target->middle_name,
                'suffix' => $target->suffix,
                'email' => $target->email,
                'password' => '',
                'password_confirmation' => '',
                'college_id' => $target->college_id,
                'role' => 'registrar',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        expect($target->fresh()->hasRole('registrar'))->toBeTrue();
    });

    it('super_admin can deactivate a user (is_active = false)', function () {
        $admin = adminMakeSuperAdmin();
        $target = User::factory()->create([
            'is_active' => true,
            'employee_number' => fake()->unique()->bothify('??####'),
            'first_name' => 'DEACT',
            'last_name' => 'IVE',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $target->assignRole('viewer');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'employee_number' => $target->employee_number,
                'first_name' => $target->first_name,
                'last_name' => $target->last_name,
                'middle_name' => $target->middle_name,
                'suffix' => $target->suffix,
                'email' => $target->email,
                'password' => '',
                'password_confirmation' => '',
                'college_id' => $target->college_id,
                'role' => 'viewer',
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.users.index'));

        expect($target->fresh()->is_active)->toBeFalse();
    });

    it('non-super_admin cannot access user management routes', function () {
        $user = adminMakeNonSuperAdmin('faculty');

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.users.store'), [])
            ->assertForbidden();
    });

    it('created user is stored with uppercase name fields', function () {
        $admin = adminMakeSuperAdmin();

        $employeeNumber = fake()->unique()->bothify('??####');
        $email = fake()->unique()->safeEmail();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'employee_number' => $employeeNumber,
                'first_name' => 'lowercase',
                'last_name' => 'names',
                'middle_name' => 'middle',
                'suffix' => null,
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'college_id' => null,
                'role' => 'co_author',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', $email)->firstOrFail();

        expect($created->first_name)->toBe('LOWERCASE')
            ->and($created->last_name)->toBe('NAMES')
            ->and($created->middle_name)->toBe('MIDDLE')
            ->and($created->name)->toContain('LOWERCASE')
            ->and($created->name)->toContain('NAMES');
    });
});

// ─────────────────────────────────────────────
// COLLEGE MANAGEMENT
// ─────────────────────────────────────────────

describe('Admin college management', function () {

    it('super_admin can view colleges list', function () {
        $admin = adminMakeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('admin.colleges.index'))
            ->assertOk();
    });

    it('super_admin can create a new college', function () {
        $admin = adminMakeSuperAdmin();
        $code = strtoupper(fake()->unique()->regexify('[A-Z]{2}[0-9]{3}'));
        $name = fake()->unique()->words(4, true);

        $this->actingAs($admin)
            ->post(route('admin.colleges.store'), [
                'code' => $code,
                'name' => $name,
            ])
            ->assertRedirect(route('admin.colleges.index'));

        expect(College::query()->where('code', $code)->exists())->toBeTrue();
    });

    it('super_admin can edit a college', function () {
        $admin = adminMakeSuperAdmin();
        $college = College::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.colleges.edit', $college))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $college->id,
                'code' => $college->code,
            ]);
    });

    it('super_admin can toggle a college active/inactive', function () {
        $admin = adminMakeSuperAdmin();
        $college = College::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.colleges.toggle-active', $college))
            ->assertRedirect(route('admin.colleges.index'));

        expect($college->fresh()->is_active)->toBeFalse();

        $this->actingAs($admin)
            ->post(route('admin.colleges.toggle-active', $college->fresh()))
            ->assertRedirect(route('admin.colleges.index'));

        expect($college->fresh()->is_active)->toBeTrue();
    });

    it('super_admin can delete a college with no associated research', function () {
        $admin = adminMakeSuperAdmin();
        $college = College::factory()->create(['is_active' => true]);
        expect($college->programs()->count())->toBe(0);

        $this->actingAs($admin)
            ->delete(route('admin.colleges.destroy', $college))
            ->assertRedirect(route('admin.colleges.index'));

        expect(College::query()->whereKey($college->id)->exists())->toBeFalse();
    });

    it('college name and code are stored uppercase', function () {
        $admin = adminMakeSuperAdmin();
        $code = fake()->unique()->regexify('[a-z]{2}[0-9]{3}');
        $name = 'mixed Case college Name';

        $this->actingAs($admin)
            ->post(route('admin.colleges.store'), [
                'code' => $code,
                'name' => $name,
            ])
            ->assertRedirect(route('admin.colleges.index'));

        $college = College::query()->latest('id')->firstOrFail();

        expect($college->code)->toBe(strtoupper($code))
            ->and($college->name)->toBe('MIXED CASE COLLEGE NAME');
    });

    it('non-super_admin cannot create a college', function () {
        $user = adminMakeNonSuperAdmin('ovpri_admin');
        $code = strtoupper(fake()->unique()->regexify('[A-Z]{2}[0-9]{3}'));

        $this->actingAs($user)
            ->post(route('admin.colleges.store'), [
                'code' => $code,
                'name' => 'BLOCKED COLLEGE',
            ])
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// PROGRAM MANAGEMENT
// ─────────────────────────────────────────────

describe('Admin program management', function () {

    it('super_admin can create a program under a college', function () {
        $admin = adminMakeSuperAdmin();
        $college = College::factory()->create(['is_active' => true]);
        $code = strtoupper(fake()->unique()->regexify('[A-Z]{3}[0-9]{2}'));
        $name = fake()->unique()->words(3, true);

        $this->actingAs($admin)
            ->post(route('admin.programs.store'), [
                'college_id' => $college->id,
                'code' => $code,
                'name' => $name,
            ])
            ->assertRedirect(route('admin.colleges.index'));

        $program = Program::query()->where('code', strtoupper($code))->firstOrFail();
        expect((int) $program->college_id)->toBe((int) $college->id)
            ->and($program->code)->toBe(strtoupper($code))
            ->and($program->name)->toBe(strtoupper($name));
    });

    it('super_admin can edit a program', function () {
        $admin = adminMakeSuperAdmin();
        $college = College::factory()->create(['is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id,
            'code' => strtoupper(fake()->unique()->regexify('[A-Z]{3}[0-9]{2}')),
            'name' => 'ORIGINAL PROGRAM TITLE',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.programs.edit', $program))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $program->id,
                'college_id' => $college->id,
            ]);
    });

    it('super_admin can delete a program', function () {
        $admin = adminMakeSuperAdmin();
        $college = College::factory()->create(['is_active' => true]);
        $program = Program::create([
            'college_id' => $college->id,
            'code' => strtoupper(fake()->unique()->regexify('[A-Z]{3}[0-9]{2}')),
            'name' => 'TO DELETE',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.programs.destroy', $program))
            ->assertRedirect(route('admin.colleges.index'));

        expect(Program::query()->whereKey($program->id)->exists())->toBeFalse();
    });

    it('program is linked to the correct college', function () {
        $admin = adminMakeSuperAdmin();
        $collegeA = College::factory()->create(['is_active' => true]);
        $collegeB = College::factory()->create(['is_active' => true]);
        $code = strtoupper(fake()->unique()->regexify('[A-Z]{3}[0-9]{2}'));

        $this->actingAs($admin)
            ->post(route('admin.programs.store'), [
                'college_id' => $collegeB->id,
                'code' => $code,
                'name' => 'LINKED PROGRAM',
            ])
            ->assertRedirect(route('admin.colleges.index'));

        $program = Program::query()->where('code', $code)->firstOrFail();
        expect((int) $program->college_id)->toBe((int) $collegeB->id)
            ->and((int) $program->college_id)->not->toBe((int) $collegeA->id);
    });

    it('non-super_admin cannot create a program', function () {
        $user = adminMakeNonSuperAdmin('college_dean');
        $college = College::factory()->create(['is_active' => true]);
        $code = strtoupper(fake()->unique()->regexify('[A-Z]{3}[0-9]{2}'));

        $this->actingAs($user)
            ->post(route('admin.programs.store'), [
                'college_id' => $college->id,
                'code' => $code,
                'name' => 'UNAUTHORIZED',
            ])
            ->assertForbidden();
    });
});

// ─────────────────────────────────────────────
// AUDIT LOGS
// ─────────────────────────────────────────────

describe('Admin audit logs', function () {

    it('super_admin can view the audit logs index', function () {
        $admin = adminMakeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertOk();
    });

    it('non-super_admin cannot access audit logs', function () {
        $user = adminMakeNonSuperAdmin('faculty');

        $this->actingAs($user)
            ->get(route('audit.index'))
            ->assertForbidden();
    });

    it('audit log index is paginated and filterable', function () {
        $admin = adminMakeSuperAdmin();

        for ($i = 0; $i < 26; $i++) {
            adminCreateAuditLog([
                'action' => 'paginate_batch',
                'auditable_id' => $i + 1,
                'created_at' => now()->subSeconds($i),
            ]);
        }

        adminCreateAuditLog(['action' => 'unique_filter_action', 'auditable_id' => 999]);

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->perPage() === 25
                && $logs->total() >= 27
                && $logs->hasPages());

        $this->actingAs($admin)
            ->get(route('audit.index', ['action' => 'unique_filter_action']))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->getCollection()->every(
                fn ($log) => $log->action === 'unique_filter_action'
            ));
    });
});
