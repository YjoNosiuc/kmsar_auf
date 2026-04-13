<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $ccs  = College::where('code', 'CCS')->firstOrFail();
        $cba  = College::where('code', 'CBA')->firstOrFail();
        $cea  = College::where('code', 'CEA')->firstOrFail();

        $rows = [
            // ── Super Admin ───────────────────────────────────────
            [
                'employee_number' => 'AUF-0001',
                'first_name'      => 'ADMIN',
                'last_name'       => 'USER',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'admin@auf.edu.ph',
                'role'            => 'super_admin',
                'college_id'      => null,
            ],

            // ── OVPRI / CDAIC ─────────────────────────────────────
            [
                'employee_number' => 'AUF-0002',
                'first_name'      => 'LUZ',
                'last_name'       => 'AQUINO',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'ovpri@auf.edu.ph',
                'role'            => 'ovpri_admin',
                'college_id'      => null,
            ],
            [
                'employee_number' => 'AUF-0003',
                'first_name'      => 'RAMON',
                'last_name'       => 'CASTRO',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'cdaic@auf.edu.ph',
                'role'            => 'cdaic_admin',
                'college_id'      => null,
            ],

            // ── College Deans ─────────────────────────────────────
            [
                'employee_number' => 'AUF-0010',
                'first_name'      => 'JOSE',
                'last_name'       => 'RIVERA',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'dean.ccs@auf.edu.ph',
                'role'            => 'college_dean',
                'college_id'      => $ccs->id,
            ],
            [
                'employee_number' => 'AUF-0011',
                'first_name'      => 'ANA',
                'last_name'       => 'REYES',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'dean.cba@auf.edu.ph',
                'role'            => 'college_dean',
                'college_id'      => $cba->id,
            ],
            [
                'employee_number' => 'AUF-0012',
                'first_name'      => 'ROBERTO',
                'last_name'       => 'MENDOZA',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'dean.cea@auf.edu.ph',
                'role'            => 'college_dean',
                'college_id'      => $cea->id,
            ],

            // ── CCS Faculty ───────────────────────────────────────
            [
                'employee_number' => 'AUF-0020',
                'first_name'      => 'MARIA',
                'last_name'       => 'SANTOS',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'faculty.ccs1@auf.edu.ph',
                'role'            => 'faculty',
                'college_id'      => $ccs->id,
            ],
            [
                'employee_number' => 'AUF-0021',
                'first_name'      => 'JUAN',
                'last_name'       => 'DELA CRUZ',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'faculty.ccs2@auf.edu.ph',
                'role'            => 'faculty',
                'college_id'      => $ccs->id,
            ],
            [
                'employee_number' => 'AUF-0022',
                'first_name'      => 'ANNA',
                'last_name'       => 'REYES',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'faculty.ccs3@auf.edu.ph',
                'role'            => 'faculty',
                'college_id'      => $ccs->id,
            ],

            // ── CBA Faculty ───────────────────────────────────────
            [
                'employee_number' => 'AUF-0030',
                'first_name'      => 'CARLOS',
                'last_name'       => 'BAUTISTA',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'faculty.cba1@auf.edu.ph',
                'role'            => 'faculty',
                'college_id'      => $cba->id,
            ],
            [
                'employee_number' => 'AUF-0031',
                'first_name'      => 'LIZA',
                'last_name'       => 'FERNANDEZ',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'faculty.cba2@auf.edu.ph',
                'role'            => 'faculty',
                'college_id'      => $cba->id,
            ],
            [
                'employee_number' => 'AUF-0032',
                'first_name'      => 'MARCO',
                'last_name'       => 'VILLANUEVA',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'faculty.cba3@auf.edu.ph',
                'role'            => 'faculty',
                'college_id'      => $cba->id,
            ],

            // ── CEA Faculty ───────────────────────────────────────
            [
                'employee_number' => 'AUF-0040',
                'first_name'      => 'PEDRO',
                'last_name'       => 'GARCIA',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'faculty.cea1@auf.edu.ph',
                'role'            => 'faculty',
                'college_id'      => $cea->id,
            ],
            [
                'employee_number' => 'AUF-0041',
                'first_name'      => 'SOFIA',
                'last_name'       => 'LIM',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'faculty.cea2@auf.edu.ph',
                'role'            => 'faculty',
                'college_id'      => $cea->id,
            ],
            [
                'employee_number' => 'AUF-0042',
                'first_name'      => 'MARK',
                'last_name'       => 'TORRES',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'faculty.cea3@auf.edu.ph',
                'role'            => 'faculty',
                'college_id'      => $cea->id,
            ],

            // ── Registrar ─────────────────────────────────────────
            [
                'employee_number' => 'AUF-0050',
                'first_name'      => 'ROSA',
                'last_name'       => 'MAGNO',
                'middle_name'     => null,
                'suffix'          => null,
                'email'           => 'registrar@auf.edu.ph',
                'role'            => 'registrar',
                'college_id'      => null,
            ],
        ];

        foreach ($rows as $row) {
            $role = $row['role'];
            unset($row['role']);

            $user = User::updateOrCreate(
                ['email' => $row['email']],
                array_merge($row, [
                    'name'               => $row['first_name']
                                            .' '.$row['last_name'],
                    'password'           => bcrypt('password'),
                    'is_active'          => true,
                    'email_verified_at'  => now(),
                ])
            );

            $user->syncRoles([$role]);
        }
    }
}
