<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\KmsarUserManagement;
use Illuminate\Console\Command;

class AuditUserRoleAlignment extends Command
{
    protected $signature = 'kmsar:audit-user-roles
                            {--fix : Apply suggested user_type and role corrections}
                            {--include-legacy : Include registrar and co_author accounts in fix output}';

    protected $description = 'Audit KMSAR users for user_type vs role mismatches and optionally fix them';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $includeLegacy = (bool) $this->option('include-legacy');

        $users = User::query()
            ->with('roles')
            ->orderBy('email')
            ->get();

        $rows = [];
        $issueCount = 0;
        $fixedCount = 0;
        $skippedLegacy = 0;

        foreach ($users as $user) {
            $role = $user->getRoleNames()->first();
            $userType = $user->user_type;
            $issues = KmsarUserManagement::auditIssuesForUser($userType, $role);

            if ($issues === []) {
                continue;
            }

            $issueCount++;
            $suggested = KmsarUserManagement::suggestedFix($userType, $role, $issues);

            $rows[] = [
                'id' => (string) $user->id,
                'email' => $user->email,
                'user_type' => $userType ?? '—',
                'role' => $role ?? '—',
                'issues' => implode(', ', $issues),
                'suggested_type' => $suggested['user_type'] ?? '—',
                'suggested_role' => $suggested['role'] ?? '—',
            ];

            if (! $fix) {
                continue;
            }

            if (in_array('legacy_system_role', $issues, true) && ! $includeLegacy) {
                $skippedLegacy++;

                continue;
            }

            if ($suggested['user_type'] !== null && $suggested['user_type'] !== $userType) {
                $user->user_type = $suggested['user_type'];
            }

            if ($suggested['role'] !== null) {
                $user->save();
                $user->syncRoles([$suggested['role']]);
                $fixedCount++;

                continue;
            }

            $user->save();
        }

        if ($rows === []) {
            $this->info('All users have aligned user_type and role values.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Email', 'User Type', 'Role', 'Issues', 'Suggested Type', 'Suggested Role'],
            $rows
        );

        $this->newLine();
        $this->warn("Found {$issueCount} user(s) with alignment issues.");

        if ($fix) {
            $this->info("Fixed {$fixedCount} user(s).");
            if ($skippedLegacy > 0) {
                $this->comment("Skipped {$skippedLegacy} legacy system role account(s). Re-run with --include-legacy to adjust those manually in the database.");
            }
        } else {
            $this->comment('Run with --fix to apply suggested corrections.');
        }

        return self::SUCCESS;
    }
}
