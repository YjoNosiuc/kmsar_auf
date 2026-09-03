<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Program;
use App\Models\User;
use App\Support\KmsarUserManagement;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $ccs = College::query()->where('code', 'CCS')->firstOrFail();
        $camp = College::query()->where('code', 'CAMP')->firstOrFail();

        $bsit = Program::query()->where('code', 'BSIT')->first();
        $bscs = Program::query()->where('code', 'BSCS')->first();
        $bspt = Program::query()->where('code', 'BSPT')->first();
        $bsmt = Program::query()->where('code', 'BSMT')->first();

        $rows = [
            [
                'employee_number' => '1001',
                'first_name' => 'ADMIN',
                'last_name' => 'USER',
                'email' => 'admin@yopmail.com',
                'role' => 'super_admin',
                'user_type' => 'staff',
                'college_id' => null,
                'program_id' => null,
            ],
            [
                'employee_number' => '1002',
                'first_name' => 'LUZ',
                'last_name' => 'AQUINO',
                'email' => 'ovpri@yopmail.com',
                'role' => 'ovpri_admin',
                'user_type' => 'staff',
                'college_id' => null,
                'program_id' => null,
            ],
            [
                'employee_number' => '1003',
                'first_name' => 'RAMON',
                'last_name' => 'CASTRO',
                'email' => 'cdaic@yopmail.com',
                'role' => 'cdaic_admin',
                'user_type' => 'staff',
                'college_id' => null,
                'program_id' => null,
            ],
            [
                'employee_number' => '1010',
                'first_name' => 'JOSE',
                'last_name' => 'RIVERA',
                'email' => 'dean.ccs@yopmail.com',
                'role' => 'college_dean',
                'user_type' => 'faculty',
                'college_id' => $ccs->id,
                'program_id' => $bsit?->id,
            ],
            [
                'employee_number' => '1011',
                'first_name' => 'TERESA',
                'last_name' => 'RAMOS',
                'email' => 'dean.camp@yopmail.com',
                'role' => 'college_dean',
                'user_type' => 'faculty',
                'college_id' => $camp->id,
                'program_id' => $bspt?->id,
            ],
            [
                'employee_number' => '1020',
                'first_name' => 'MARIA',
                'last_name' => 'SANTOS',
                'email' => 'faculty.ccs1@yopmail.com',
                'role' => 'faculty',
                'user_type' => 'faculty',
                'college_id' => $ccs->id,
                'program_id' => $bsit?->id,
            ],
            [
                'employee_number' => '1021',
                'first_name' => 'JUAN',
                'last_name' => 'DELA CRUZ',
                'email' => 'faculty.ccs2@yopmail.com',
                'role' => 'faculty',
                'user_type' => 'faculty',
                'college_id' => $ccs->id,
                'program_id' => $bscs?->id,
            ],
            [
                'employee_number' => '1030',
                'first_name' => 'ELENA',
                'last_name' => 'CRUZ',
                'email' => 'faculty.camp1@yopmail.com',
                'role' => 'faculty',
                'user_type' => 'faculty',
                'college_id' => $camp->id,
                'program_id' => $bspt?->id,
            ],
            [
                'employee_number' => '1031',
                'first_name' => 'PAOLO',
                'last_name' => 'REYES',
                'email' => 'faculty.camp2@yopmail.com',
                'role' => 'faculty',
                'user_type' => 'faculty',
                'college_id' => $camp->id,
                'program_id' => $bsmt?->id,
            ],
            [
                'employee_number' => '1040',
                'first_name' => 'SAMPLE',
                'last_name' => 'STUDENT',
                'email' => 'student.viewer@yopmail.com',
                'role' => 'viewer',
                'user_type' => 'student',
                'college_id' => $ccs->id,
                'program_id' => $bsit?->id,
            ],
            [
                'employee_number' => null,
                'first_name' => 'EXTERNAL',
                'last_name' => 'AFFILIATE',
                'email' => 'external.viewer@yopmail.com',
                'role' => 'viewer',
                'user_type' => 'external_affiliate',
                'institution' => 'Partner University',
                'college_id' => $ccs->id,
                'program_id' => null,
            ],
        ];

        foreach ($rows as $row) {
            $role = $row['role'];
            $userType = $row['user_type'];

            if (! KmsarUserManagement::isRoleAllowedForUserType($userType, $role)) {
                throw new InvalidArgumentException(
                    "UserSeeder misconfiguration: role [{$role}] is not allowed for user_type [{$userType}] ({$row['email']})."
                );
            }

            unset($row['role']);

            $user = User::updateOrCreate(
                ['email' => $row['email']],
                array_merge($row, [
                    'middle_name' => null,
                    'suffix' => null,
                    'name' => $row['first_name'].' '.$row['last_name'],
                    'password' => 'password',
                    'is_active' => true,
                    'is_pending' => false,
                    'email_verified_at' => now(),
                ])
            );

            $user->syncRoles([$role]);
        }

        $ccs->update(['head_user_id' => User::query()->where('email', 'dean.ccs@yopmail.com')->value('id')]);
        $camp->update(['head_user_id' => User::query()->where('email', 'dean.camp@yopmail.com')->value('id')]);
    }
}
