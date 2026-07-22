<?php

namespace App\Imports;

use App\Models\College;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;

class UserImport implements OnEachRow, WithHeadingRow, WithStartRow
{
    private const VALID_ROLES = [
        'super_admin',
        'ovpri_admin',
        'cdaic_admin',
        'college_dean',
        'unit_head',
        'faculty',
        'co_author',
        'registrar',
        'viewer',
    ];

    public int $imported = 0;

    /**
     * @var list<array{row: int, value: string, reason: string}>
     */
    public array $skipped = [];

    public function startRow(): int
    {
        return 3;
    }

    public function onRow(Row $row): void
    {
        $rowNumber = $row->getIndex();
        $data = $row->toArray();

        try {
            $name = trim((string) ($data['name'] ?? ''));
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $employeeNumber = strtoupper(trim((string) ($data['employee_number'] ?? '')));
            $collegeCode = strtoupper(trim((string) ($data['college_code'] ?? '')));
            $officeRaw = trim((string) ($data['office'] ?? ''));
            $roleRaw = strtolower(trim((string) ($data['role'] ?? '')));
            $passwordRaw = trim((string) ($data['password'] ?? ''));

            if ($name === '' && $email === '' && $employeeNumber === '' && $collegeCode === '') {
                return;
            }

            if ($name === '' || $email === '') {
                $this->skip($rowNumber, $email !== '' ? $email : $name, 'Name or email is blank');

                return;
            }

            if (User::query()->where('email', $email)->exists()) {
                $this->skip($rowNumber, $email, 'Email already exists');

                return;
            }

            if ($employeeNumber !== '' && User::query()->where('employee_number', $employeeNumber)->exists()) {
                $this->skip($rowNumber, $employeeNumber, 'Employee number already exists');

                return;
            }

            $college = College::query()
                ->where('is_active', true)
                ->where('code', $collegeCode)
                ->first();

            if ($college === null) {
                $this->skip($rowNumber, $collegeCode !== '' ? $collegeCode : '(blank)', 'College code not found in active colleges');

                return;
            }

            $role = in_array($roleRaw, self::VALID_ROLES, true) ? $roleRaw : 'faculty';
            $office = $officeRaw !== '' ? $officeRaw : null;
            $password = $passwordRaw !== '' ? $passwordRaw : 'password';

            DB::transaction(function () use ($name, $email, $employeeNumber, $college, $office, $password, $role) {
                $user = User::query()->create([
                    'name' => strtoupper($name),
                    'email' => $email,
                    'employee_number' => $employeeNumber !== '' ? $employeeNumber : null,
                    'college_id' => $college->id,
                    'office' => $office,
                    'password' => bcrypt($password),
                    'is_active' => true,
                ]);

                $user->assignRole($role);
            });

            $this->imported++;
        } catch (\Throwable $e) {
            $value = strtolower(trim((string) ($data['email'] ?? '')));
            if ($value === '') {
                $value = trim((string) ($data['name'] ?? ''));
            }

            $this->skip($rowNumber, $value !== '' ? $value : '(unknown)', $e->getMessage());
        }
    }

    /**
     * @return array{imported: int, skipped: list<array{row: int, value: string, reason: string}>}
     */
    public function results(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
        ];
    }

    private function skip(int $row, string $value, string $reason): void
    {
        $this->skipped[] = [
            'row' => $row,
            'value' => $value,
            'reason' => $reason,
        ];
    }
}
