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
use App\Models\SmtpSetting;
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

    it('does not list registrar or co_author accounts on the users table', function () {
        $admin = adminMakeSuperAdmin();
        $registrar = User::factory()->create([
            'email' => 'hidden.registrar@example.com',
            'is_active' => true,
            'is_pending' => false,
        ]);
        $registrar->assignRole('registrar');

        $coAuthor = User::factory()->create([
            'email' => 'hidden.coauthor@example.com',
            'is_active' => true,
            'is_pending' => false,
        ]);
        \Spatie\Permission\Models\Role::findOrCreate('co_author', 'web');
        $coAuthor->assignRole('co_author');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('hidden.registrar@example.com')
            ->assertDontSee('hidden.coauthor@example.com');
    });

    it('super_admin can create a new user with a role', function () {
        $admin = adminMakeSuperAdmin();

        $employeeNumber = fake()->unique()->numerify('##########');
        $email = fake()->unique()->safeEmail();
        $college = makeCollege(false);

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
                'college_id' => $college->id,
                'user_type' => 'faculty',
                'role' => 'faculty',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', $email)->first();
        expect($created)->not->toBeNull()
            ->and($created->hasRole('faculty'))->toBeTrue()
            ->and($created->is_pending)->toBeFalse()
            ->and($created->is_active)->toBeTrue();
    });

    it('super_admin can edit an existing user', function () {
        $admin = adminMakeSuperAdmin();
        $target = User::factory()->create([
            'is_active' => true,
            'employee_number' => fake()->unique()->numerify('##########'),
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
            'user_type' => 'student',
            'employee_number' => fake()->unique()->numerify('##########'),
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
                'user_type' => 'faculty',
                'role' => 'faculty',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        expect($target->fresh()->hasRole('faculty'))->toBeTrue();
    });

    it('super_admin can deactivate a user (is_active = false)', function () {
        $admin = adminMakeSuperAdmin();
        $target = User::factory()->create([
            'is_active' => true,
            'user_type' => 'student',
            'employee_number' => fake()->unique()->numerify('##########'),
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
                'user_type' => 'student',
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

    it('created user is stored with name fields as typed', function () {
        $admin = adminMakeSuperAdmin();

        $employeeNumber = fake()->unique()->numerify('##########');
        $email = fake()->unique()->safeEmail();
        $college = makeCollege(false);

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
                'college_id' => $college->id,
                'user_type' => 'student',
                'role' => 'viewer',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', $email)->firstOrFail();

        expect($created->first_name)->toBe('lowercase')
            ->and($created->last_name)->toBe('names')
            ->and($created->middle_name)->toBe('middle')
            ->and($created->name)->toContain('lowercase')
            ->and($created->name)->toContain('names');
    });

    it('rejects a role that is not allowed for the selected user type', function () {
        $admin = adminMakeSuperAdmin();
        $college = makeCollege(false);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'employee_number' => fake()->unique()->numerify('##########'),
                'first_name' => 'Student',
                'last_name' => 'User',
                'email' => fake()->unique()->safeEmail(),
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'college_id' => $college->id,
                'user_type' => 'student',
                'role' => 'super_admin',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('role');
    });

    it('rejects assigning faculty role to student user type on update', function () {
        $admin = adminMakeSuperAdmin();
        $target = User::factory()->create([
            'is_active' => true,
            'user_type' => 'student',
            'first_name' => 'STUDENT',
            'last_name' => 'USER',
            'employee_number' => fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $target->assignRole('viewer');

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.update', $target), [
                'employee_number' => $target->employee_number,
                'first_name' => 'STUDENT',
                'last_name' => 'USER',
                'middle_name' => $target->middle_name,
                'suffix' => $target->suffix,
                'email' => $target->email,
                'password' => '',
                'password_confirmation' => '',
                'college_id' => $target->college_id,
                'user_type' => 'student',
                'role' => 'faculty',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('role');
    });

    it('programs api returns programs for the selected college', function () {
        $college = College::factory()->create();
        $otherCollege = College::factory()->create();

        $program = Program::query()->create([
            'college_id' => $college->id,
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
            'is_active' => true,
        ]);
        Program::query()->create([
            'college_id' => $otherCollege->id,
            'code' => 'BSA',
            'name' => 'BS Accountancy',
            'is_active' => true,
        ]);

        $this->getJson(route('api.programs', ['college_id' => $college->id]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $program->id,
                'code' => 'BSIT',
            ]);
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

// ─────────────────────────────────────────────
// SMTP SETTINGS
// ─────────────────────────────────────────────

describe('Admin SMTP settings', function () {

    it('super_admin can view the SMTP settings page', function () {
        $admin = adminMakeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('admin.smtp-settings.edit'))
            ->assertOk()
            ->assertViewIs('admin.smtp-settings.edit')
            ->assertViewHas('presets');
    });

    it('denies non super_admin access to SMTP settings', function () {
        $dean = adminMakeNonSuperAdmin('college_dean');

        $this->actingAs($dean)
            ->get(route('admin.smtp-settings.edit'))
            ->assertForbidden();
    });

    it('super_admin can save SMTP settings', function () {
        $admin = adminMakeSuperAdmin();

        $this->actingAs($admin)
            ->put(route('admin.smtp-settings.update'), [
                'is_enabled' => '1',
                'preset' => 'mailtrap_sandbox',
                'mail_mailer' => 'smtp',
                'mail_host' => 'sandbox.smtp.mailtrap.io',
                'mail_port' => 2525,
                'mail_username' => 'test-user',
                'mail_password' => 'secret-pass',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'noreply@kmsar.auf.edu.ph',
                'mail_from_name' => 'KMSAR',
            ])
            ->assertRedirect(route('admin.smtp-settings.edit'))
            ->assertSessionHas('success');

        $settings = SmtpSetting::query()->first();

        expect($settings)->not->toBeNull()
            ->and($settings->mail_host)->toBe('sandbox.smtp.mailtrap.io')
            ->and($settings->mail_username)->toBe('test-user')
            ->and($settings->updated_by)->toBe($admin->id);
    });

    it('applies database SMTP settings to runtime config when enabled', function () {
        SmtpSetting::query()->create([
            'is_enabled' => true,
            'preset' => 'mailtrap_sandbox',
            'mail_mailer' => 'smtp',
            'mail_host' => 'sandbox.smtp.mailtrap.io',
            'mail_port' => 2525,
            'mail_username' => 'db-user',
            'mail_password' => 'db-pass',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@kmsar.auf.edu.ph',
            'mail_from_name' => 'KMSAR Test',
        ]);

        app(\App\Services\SmtpSettingsService::class)->applyToConfig();

        expect(config('mail.mailers.smtp.host'))->toBe('sandbox.smtp.mailtrap.io')
            ->and(config('mail.mailers.smtp.username'))->toBe('db-user')
            ->and(config('mail.from.name'))->toBe('KMSAR Test');
    });
});
