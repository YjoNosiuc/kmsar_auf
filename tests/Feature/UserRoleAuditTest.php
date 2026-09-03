<?php

use App\Models\User;
use App\Support\KmsarUserManagement;

describe('KMSAR user role audit command', function () {

    it('reports no issues when all users are aligned', function () {
        $user = User::factory()->create([
            'user_type' => 'faculty',
            'first_name' => 'ALIGNED',
            'last_name' => 'FACULTY',
            'email' => 'aligned.faculty@example.com',
        ]);
        $user->assignRole('faculty');

        $this->artisan('kmsar:audit-user-roles')
            ->expectsOutput('All users have aligned user_type and role values.')
            ->assertSuccessful();
    });

    it('reports a role and user_type mismatch', function () {
        $user = User::factory()->create([
            'user_type' => 'student',
            'first_name' => 'MISMATCH',
            'last_name' => 'USER',
            'email' => 'mismatch.student@example.com',
        ]);
        $user->assignRole('faculty');

        $this->artisan('kmsar:audit-user-roles')
            ->expectsOutputToContain('mismatch.student@example.com')
            ->expectsOutputToContain('Found 1 user(s) with alignment issues.')
            ->assertSuccessful();
    });

    it('fixes a mismatch with the --fix option', function () {
        $user = User::factory()->create([
            'user_type' => 'student',
            'first_name' => 'FIX',
            'last_name' => 'ME',
            'email' => 'fix.me@example.com',
        ]);
        $user->assignRole('faculty');

        $this->artisan('kmsar:audit-user-roles --fix')
            ->expectsOutputToContain('fix.me@example.com')
            ->assertSuccessful();

        $user->refresh();
        expect($user->user_type)->toBe('student')
            ->and($user->hasRole('viewer'))->toBeTrue();
    });

    it('validates seeded demo accounts after fresh seed', function () {
        $this->seed([
            \Database\Seeders\CollegeSeeder::class,
            \Database\Seeders\ProgramSeeder::class,
            \Database\Seeders\UserSeeder::class,
        ]);

        $emails = [
            'admin@yopmail.com' => ['staff', 'super_admin'],
            'faculty.ccs1@yopmail.com' => ['faculty', 'faculty'],
            'student.viewer@yopmail.com' => ['student', 'viewer'],
            'external.viewer@yopmail.com' => ['external_affiliate', 'viewer'],
        ];

        foreach ($emails as $email => [$userType, $role]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            expect($user->user_type)->toBe($userType)
                ->and($user->hasRole($role))->toBeTrue()
                ->and(KmsarUserManagement::isRoleAllowedForUserType($user->user_type, $role))->toBeTrue();
        }
    });
});
