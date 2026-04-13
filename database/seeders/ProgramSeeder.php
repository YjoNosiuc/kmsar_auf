<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * Official AUF programs by college — KMSAR_ARCHITECTURE.md §5 (ProgramSeeder) / AUF programs registry.
 */
class ProgramSeeder extends Seeder
{
    /**
     * College code => list of [program code, full program name].
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private const PROGRAMS_BY_COLLEGE = [
        'CAMP' => [
            ['BSClinPhar', 'Bachelor of Science in Clinical Pharmacy'],
            ['BSMT',       'Bachelor of Science in Medical Technology'],
            ['BSOT',       'Bachelor of Science in Occupational Therapy'],
            ['PHARM',      'Bachelor of Science in Pharmacy'],
            ['BSPT',       'Bachelor of Science in Physical Therapy'],
            ['BSRT',       'Bachelor of Science in Radiologic Technology'],
        ],
        'CAS' => [
            ['AB Com',           'Bachelor of Arts in Communication'],
            ['BSBIO',            'Bachelor of Science in Biology'],
            ['BS Human Bio',     'Bachelor of Science in Human Biology'],
            ['BSPSY',            'Bachelor of Science in Psychology'],
            ['SABPsych-MAPsych', 'Straight Bachelor of Arts in Psychology - Master of Arts in Psychology'],
        ],
        'CBA' => [
            ['BSA',  'Bachelor of Science in Accountancy'],
            ['BSBA', 'Bachelor of Science in Business Administration'],
            ['BSHM', 'Bachelor of Science in Hospitality Management'],
            ['BSMA', 'Bachelor of Science in Management Accounting'],
            ['BSTM', 'Bachelor of Science in Tourism Management'],
        ],
        'CCS' => [
            ['BMMA', 'Bachelor of Multimedia Arts'],
            ['BSCS', 'Bachelor of Science in Computer Science'],
            ['BSIT', 'Bachelor of Science in Information Technology'],
        ],
        'CCJE' => [
            ['BSCRM', 'Bachelor of Science in Criminology'],
        ],
        'CED' => [
            ['BEEd',  'Bachelor of Elementary Education'],
            ['BSEd',  'Bachelor of Secondary Education'],
            ['BSNEd', 'Bachelor of Special Needs Education'],
        ],
        'CEA' => [
            ['BSARCHI', 'Bachelor of Science in Architecture'],
            ['BSCE',    'Bachelor of Science in Civil Engineering'],
            ['BSCOE',   'Bachelor of Science in Computer Engineering'],
            ['BS ECE',  'Bachelor of Science in Electronics Engineering'],
        ],
        'GS' => [
            ['MAEd',        'Master of Arts in Education'],
            ['MAN',         'Master of Arts in Nursing'],
            ['MAT-PE',      'Master of Arts in Teaching Physical Education'],
            ['MASE',        'Master of Arts in Special Education'],
            ['MAPsych',     'Master of Arts in Psychology'],
            ['MBA',         'Master in Business Administration'],
            ['MDS',         'Master in Data Science'],
            ['MIT',         'Master in Information Technology'],
            ['MN',          'Master in Nursing'],
            ['MPA',         'Master in Public Administration'],
            ['MPH',         'Master in Public Health'],
            ['MEng',        'Master of Engineering'],
            ['MSCJ',        'Master of Science in Criminal Justice'],
            ['MSMLS',       'Master of Science in Medical Laboratory Science'],
            ['MSPT',        'Master of Science in Physical Therapy'],
            ['PhD-CI-ELT',  'Doctor of Philosophy in Curriculum and Instruction major in English Language Teaching'],
            ['PhD-Educ-EM', 'Doctor of Philosophy in Education major in Educational Management'],
        ],
        'SL' => [
            ['JD', 'Juris Doctor'],
        ],
        'SM' => [
            ['MD', 'Doctor of Medicine'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PROGRAMS_BY_COLLEGE as $collegeCode => $programs) {
            $college = College::query()->where('code', $collegeCode)->first();

            if ($college === null) {
                $this->command->warn("College code [{$collegeCode}] not found; run CollegeSeeder first.");

                continue;
            }

            foreach ($programs as [$code, $name]) {
                Program::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'college_id' => $college->id,
                        'name'       => $name,
                        'is_active'  => true,
                    ]
                );
            }
        }
    }
}