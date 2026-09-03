<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class KmsarUserManagement
{
    /**
     * Legacy/system roles hidden from admin user management UI.
     *
     * @return list<string>
     */
    public static function excludedDirectoryRoles(): array
    {
        return config('kmsar.excluded_directory_roles', ['registrar', 'co_author']);
    }

    /**
     * @return array<string, string>
     */
    public static function userTypeLabels(): array
    {
        return config('kmsar.user_types', []);
    }

    /**
     * @return list<string>
     */
    public static function userTypes(): array
    {
        return array_keys(self::userTypeLabels());
    }

    /**
     * @return array<string, string>
     */
    public static function assignableRoleLabels(): array
    {
        return config('kmsar.assignable_roles', []);
    }

    /**
     * @return list<string>
     */
    public static function assignableRoles(): array
    {
        return array_keys(self::assignableRoleLabels());
    }

    public static function defaultRoleForUserType(?string $userType): string
    {
        $defaults = config('kmsar.user_type_default_roles', []);

        if ($userType !== null && array_key_exists($userType, $defaults)) {
            return (string) $defaults[$userType];
        }

        return 'faculty';
    }

    /**
     * @return list<string>
     */
    public static function allowedRolesForUserType(?string $userType): array
    {
        $map = config('kmsar.user_type_allowed_roles', []);

        if ($userType !== null && array_key_exists($userType, $map)) {
            return array_values(array_intersect($map[$userType], self::assignableRoles()));
        }

        return self::assignableRoles();
    }

    public static function isRoleAllowedForUserType(?string $userType, string $role): bool
    {
        return in_array($role, self::allowedRolesForUserType($userType), true);
    }

    public static function assertRoleAllowedForUserType(?string $userType, string $role): void
    {
        if (! self::isRoleAllowedForUserType($userType, $role)) {
            throw ValidationException::withMessages([
                'role' => [__('The selected role is not allowed for this user type.')],
            ]);
        }
    }

    public static function inferUserTypeFromRole(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        return match ($role) {
            'super_admin', 'ovpri_admin', 'cdaic_admin' => 'staff',
            'college_dean', 'unit_head', 'faculty' => 'faculty',
            'viewer' => 'student',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function auditIssuesForUser(?string $userType, ?string $role): array
    {
        $issues = [];

        if ($role === null || $role === '') {
            $issues[] = 'missing_role';

            return $issues;
        }

        if (in_array($role, self::excludedDirectoryRoles(), true)) {
            $issues[] = 'legacy_system_role';
        }

        if (! in_array($role, self::assignableRoles(), true) && ! in_array($role, ['unit_head'], true)) {
            $issues[] = 'unknown_role';
        }

        if ($userType === null || $userType === '') {
            $issues[] = 'missing_user_type';

            return $issues;
        }

        if (! in_array($userType, self::userTypes(), true)) {
            $issues[] = 'invalid_user_type';
        }

        if (in_array($role, self::assignableRoles(), true)
            && ! self::isRoleAllowedForUserType($userType, $role)) {
            $issues[] = 'role_user_type_mismatch';
        }

        return $issues;
    }

    /**
     * @param  list<string>  $issues
     * @return array{user_type: ?string, role: ?string}
     */
    public static function suggestedFix(?string $userType, ?string $role, array $issues): array
    {
        $fixedUserType = $userType;
        $fixedRole = $role;

        if (in_array('missing_user_type', $issues, true) || in_array('invalid_user_type', $issues, true)) {
            $fixedUserType = self::inferUserTypeFromRole($role) ?? 'faculty';
        }

        if (in_array('missing_role', $issues, true)) {
            $fixedRole = self::defaultRoleForUserType($fixedUserType);
        }

        if (in_array('role_user_type_mismatch', $issues, true) && $fixedUserType !== null) {
            $allowed = self::allowedRolesForUserType($fixedUserType);
            if ($role !== null && in_array($role, $allowed, true)) {
                $fixedRole = $role;
            } else {
                $fixedRole = self::defaultRoleForUserType($fixedUserType);
            }
        }

        if (in_array('legacy_system_role', $issues, true)) {
            return ['user_type' => $fixedUserType, 'role' => null];
        }

        return [
            'user_type' => $fixedUserType,
            'role' => $fixedRole,
        ];
    }
}
