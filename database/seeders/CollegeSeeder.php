<?php

namespace Database\Seeders;

use App\Models\College;
use Illuminate\Database\Seeder;

/**
 * Official AUF colleges — KMSAR_ARCHITECTURE.md §5 (CollegeSeeder) / AUF colleges registry.
 */
class CollegeSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string}>
     */
    private const COLLEGES = [
        ['code' => 'CAMP', 'name' => 'College of Allied Medical Professions'],
        ['code' => 'CAS',  'name' => 'College of Arts and Sciences'],
        ['code' => 'CBA',  'name' => 'College of Business and Accountancy'],
        ['code' => 'CCS',  'name' => 'College of Computer Studies'],
        ['code' => 'CCJE', 'name' => 'College of Criminal Justice Education'],
        ['code' => 'CED',  'name' => 'College of Education'],
        ['code' => 'CEA',  'name' => 'College of Engineering'],
        ['code' => 'GS',   'name' => 'Graduate School'],
        ['code' => 'SL',   'name' => 'School of Law'],
        ['code' => 'SM',   'name' => 'School of Medicine'],
    ];

    public function run(): void
    {
        foreach (self::COLLEGES as $row) {
            College::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name'      => $row['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}