<?php

use App\Mail\EmailVerificationMail;
use App\Mail\UserApprovedMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function approvalMakeSuperAdmin(): User
{
    $user = User::factory()->create([
        'is_active' => true,
        'is_pending' => false,
        'employee_number' => strtoupper(Str::random(8)),
        'first_name' => 'SUPER',
        'last_name' => 'ADMIN',
    ]);
    $user->assignRole('super_admin');

    return $user;
}

describe('Self-registration approval', function () {

    it('creates a pending inactive viewer after email OTP verification', function () {
        Mail::fake();
        $college = makeCollege(false);

        $this->post(route('register.store'), [
            'first_name' => 'Pending',
            'last_name' => 'Faculty',
            'middle_name' => null,
            'suffix' => null,
            'employee_number' => '1234567890',
            'college_id' => $college->id,
            'user_type' => 'faculty',
            'email' => 'pending.faculty@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect(route('register.verify-email'));

        $this->assertGuest();
        expect(User::query()->where('email', 'pending.faculty@example.com')->exists())->toBeFalse();

        Mail::assertSent(EmailVerificationMail::class);

        $otp = PasswordResetOtp::query()->where('email', 'pending.faculty@example.com')->value('otp');
        expect($otp)->toHaveLength(6);

        $this->post(route('register.confirm-email'), ['otp' => $otp])
            ->assertRedirect(route('login'))
            ->assertSessionHas('info');

        $this->assertGuest();

        $user = User::query()->where('email', 'pending.faculty@example.com')->first();
        expect($user)->not->toBeNull()
            ->and($user->is_pending)->toBeTrue()
            ->and($user->is_active)->toBeFalse()
            ->and($user->hasRole('viewer'))->toBeTrue()
            ->and($user->user_type)->toBe('faculty');
    });

    it('rejects an invalid or expired verification code', function () {
        Mail::fake();
        $college = makeCollege(false);

        $this->post(route('register.store'), [
            'first_name' => 'Pending',
            'last_name' => 'Faculty',
            'middle_name' => null,
            'suffix' => null,
            'employee_number' => '1234567891',
            'college_id' => $college->id,
            'user_type' => 'faculty',
            'email' => 'pending.badotp@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('register.verify-email'));

        $this->from(route('register.verify-email'))
            ->post(route('register.confirm-email'), ['otp' => '000000'])
            ->assertRedirect(route('register.verify-email'))
            ->assertSessionHasErrors('otp');

        expect(User::query()->where('email', 'pending.badotp@example.com')->exists())->toBeFalse();
    });

    it('saves institution for external affiliate registrations after OTP', function () {
        Mail::fake();
        $college = makeCollege(false);

        $this->post(route('register.store'), [
            'first_name' => 'External',
            'last_name' => 'Affiliate',
            'middle_name' => null,
            'suffix' => null,
            'employee_number' => null,
            'college_id' => $college->id,
            'user_type' => 'external_affiliate',
            'institution' => 'De La Salle University',
            'email' => 'pending.external@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('register.verify-email'));

        $otp = PasswordResetOtp::query()->where('email', 'pending.external@example.com')->value('otp');

        $this->post(route('register.confirm-email'), ['otp' => $otp])
            ->assertRedirect(route('login'));

        $user = User::query()->where('email', 'pending.external@example.com')->first();
        expect($user)->not->toBeNull()
            ->and($user->institution)->toBe('De La Salle University')
            ->and($user->employee_number)->toBeNull()
            ->and($user->is_pending)->toBeTrue();
    });

    it('sends a second confirmation to login after the account was already created', function () {
        Mail::fake();
        $college = makeCollege(false);

        $this->post(route('register.store'), [
            'first_name' => 'Pending',
            'last_name' => 'Faculty',
            'middle_name' => null,
            'suffix' => null,
            'employee_number' => '1234567892',
            'college_id' => $college->id,
            'user_type' => 'faculty',
            'email' => 'pending.twice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('register.verify-email'));

        $otp = PasswordResetOtp::query()->where('email', 'pending.twice@example.com')->value('otp');

        $this->post(route('register.confirm-email'), [
            'otp' => $otp,
            'email' => 'pending.twice@example.com',
        ])->assertRedirect(route('login'));

        $this->post(route('register.confirm-email'), [
            'otp' => $otp,
            'email' => 'pending.twice@example.com',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('info');

        expect(User::query()->where('email', 'pending.twice@example.com')->count())->toBe(1);
    });

    it('redirects to login when the session is gone but the pending account already exists', function () {
        $user = User::factory()->create([
            'email' => 'pending.sessionlost@example.com',
            'is_active' => false,
            'is_pending' => true,
        ]);
        $user->assignRole('viewer');

        $this->post(route('register.confirm-email'), [
            'otp' => '123456',
            'email' => 'pending.sessionlost@example.com',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('info');
    });

    it('redirects to register when verification is posted with no session and no account', function () {
        $this->post(route('register.confirm-email'), ['otp' => '123456'])
            ->assertRedirect(route('register'));
    });

    it('blocks pending users from logging in', function () {
        $user = User::factory()->create([
            'email' => 'waiting@example.com',
            'password' => 'password123',
            'is_active' => false,
            'is_pending' => true,
        ]);
        $user->assignRole('viewer');

        $this->from(route('login'))
            ->post('/login', [
                'login' => 'waiting@example.com',
                'password' => 'password123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        expect(session('errors')->first('login'))->toContain('pending approval');
    });

    it('still shows the inactive message for non-pending deactivated users', function () {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'password123',
            'is_active' => false,
            'is_pending' => false,
        ]);
        $user->assignRole('faculty');

        $this->from(route('login'))
            ->post('/login', [
                'login' => 'inactive@example.com',
                'password' => 'password123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        expect(session('errors')->first('login'))->toBe('This account is inactive.');
    });
});

describe('Admin registration approval', function () {

    it('lets super_admin approve a pending user and assign a role', function () {
        Mail::fake();

        $admin = approvalMakeSuperAdmin();
        $pending = User::factory()->create([
            'is_active' => false,
            'is_pending' => true,
            'user_type' => 'faculty',
            'email' => 'to.approve@example.com',
        ]);
        $pending->assignRole('viewer');

        $this->actingAs($admin)
            ->patch(route('admin.users.approve', $pending), ['role' => 'faculty'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $pending->refresh();
        expect($pending->is_pending)->toBeFalse()
            ->and($pending->is_active)->toBeTrue()
            ->and($pending->hasRole('faculty'))->toBeTrue();

        Mail::assertSent(UserApprovedMail::class, function (UserApprovedMail $mail) use ($pending) {
            return $mail->user->is($pending);
        });
    });

    it('rejects approving a student with a faculty role', function () {
        $admin = approvalMakeSuperAdmin();
        $pending = User::factory()->create([
            'is_active' => false,
            'is_pending' => true,
            'user_type' => 'student',
            'email' => 'student.pending@example.com',
        ]);
        $pending->assignRole('viewer');

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.approve', $pending), ['role' => 'faculty'])
            ->assertSessionHasErrors('role');

        expect($pending->fresh()->is_pending)->toBeTrue();
    });

    it('defaults pending student approval to viewer role', function () {
        Mail::fake();

        $admin = approvalMakeSuperAdmin();
        $pending = User::factory()->create([
            'is_active' => false,
            'is_pending' => true,
            'user_type' => 'student',
            'email' => 'student.auto@example.com',
        ]);
        $pending->assignRole('viewer');

        $this->actingAs($admin)
            ->patch(route('admin.users.approve', $pending))
            ->assertRedirect(route('admin.users.index'));

        expect($pending->fresh()->hasRole('viewer'))->toBeTrue();
    });

    it('lets super_admin reject a pending registration', function () {
        $admin = approvalMakeSuperAdmin();
        $pending = User::factory()->create([
            'is_active' => false,
            'is_pending' => true,
            'email' => 'to.reject@example.com',
        ]);
        $pending->assignRole('viewer');

        $this->actingAs($admin)
            ->delete(route('admin.users.reject', $pending))
            ->assertRedirect(route('admin.users.index'));

        expect(User::query()->where('email', 'to.reject@example.com')->exists())->toBeFalse();
    });

    it('does not allow approving or rejecting already-active users', function () {
        $admin = approvalMakeSuperAdmin();
        $active = User::factory()->create([
            'is_active' => true,
            'is_pending' => false,
        ]);
        $active->assignRole('faculty');

        $this->actingAs($admin)
            ->patch(route('admin.users.approve', $active), ['role' => 'viewer'])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.users.reject', $active))
            ->assertNotFound();

        expect($active->fresh())->not->toBeNull();
    });

    it('lists pending users separately from the regular directory', function () {
        $admin = approvalMakeSuperAdmin();
        $pending = User::factory()->create([
            'first_name' => 'Queued',
            'last_name' => 'Registrant',
            'name' => 'Queued Registrant',
            'is_active' => false,
            'is_pending' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Pending Registrations')
            ->assertSee($pending->email);
    });
});
