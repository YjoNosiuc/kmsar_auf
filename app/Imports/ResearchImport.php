<?php

namespace App\Imports;

use App\Models\College;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ResearchImport implements OnEachRow, WithHeadingRow, WithStartRow
{
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
            $titleRaw = trim((string) ($data['title'] ?? ''));
            $authorEmail = strtolower(trim((string) ($data['primary_author_email'] ?? '')));

            if ($titleRaw === '' && $authorEmail === '') {
                return;
            }

            if ($titleRaw === '' || $authorEmail === '') {
                $this->skip(
                    $rowNumber,
                    $titleRaw !== '' ? $titleRaw : $authorEmail,
                    'Title or primary author email is blank'
                );

                return;
            }

            $title = strtoupper($titleRaw);

            if (Research::query()->whereRaw('LOWER(title) = ?', [strtolower($title)])->exists()) {
                $this->skip($rowNumber, $title, 'Title already exists');

                return;
            }

            $author = User::query()->where('email', $authorEmail)->first();
            if ($author === null) {
                $this->skip($rowNumber, $authorEmail, 'Primary author email not found in users table');

                return;
            }

            $motherCode = strtoupper(trim((string) ($data['mother_college_code'] ?? '')));
            $motherCollege = College::query()->where('code', $motherCode)->first();
            if ($motherCollege === null) {
                $this->skip(
                    $rowNumber,
                    $motherCode !== '' ? $motherCode : '(blank)',
                    'Mother college code not found in colleges table'
                );

                return;
            }

            $registrationType = strtolower(trim((string) ($data['registration_type'] ?? '')));
            if (! in_array($registrationType, ['new', 'update'], true)) {
                $registrationType = 'new';
            }

            $classification = strtolower(trim((string) ($data['research_classification'] ?? '')));
            if ($classification === '') {
                $classification = 'other';
            }

            $fundingAgency = $this->nullableUpper($data['funding_agency'] ?? null);
            $sdgTags = $this->parseSdgTags($data['sdg_tags'] ?? '');
            $expectedOutput = $this->parsePipeList($data['expected_output'] ?? '');
            if ($expectedOutput === []) {
                $expectedOutput = ['publication'];
            }

            $expectedOutputOther = $this->nullableUpper($data['expected_output_other'] ?? null);
            if (! in_array('other', $expectedOutput, true)) {
                $expectedOutputOther = null;
            }

            $startDate = $this->parseDate($data['start_date'] ?? null);
            $endDate = $this->parseDate($data['estimated_completion_date'] ?? null);
            if ($startDate === null || $endDate === null) {
                $this->skip($rowNumber, $title, 'Invalid or blank start_date / estimated_completion_date');

                return;
            }

            $status = strtolower(trim((string) ($data['status'] ?? '')));
            if ($status === '') {
                $status = 'proposal';
            }

            $approvalStage = strtolower(trim((string) ($data['approval_stage'] ?? '')));
            if ($approvalStage === '') {
                $approvalStage = 'approved';
            }

            $isScopus = (bool) (int) ($data['is_scopus_indexed'] ?? 0);
            $otherCollegeIds = $this->parseOtherCollegeIds(
                $data['other_college_codes'] ?? '',
                (int) $motherCollege->id
            );

            $year = (int) Carbon::parse($startDate)->format('Y');
            $referenceNumber = $this->generateReferenceNumber($year, $motherCollege);

            $coauthorEmails = $this->parsePipeList($data['coauthor_emails'] ?? '');
            $coauthorCanEdit = $this->parsePipeList($data['coauthor_can_edit'] ?? '');

            $research = DB::transaction(function () use (
                $referenceNumber,
                $registrationType,
                $title,
                $author,
                $motherCollege,
                $otherCollegeIds,
                $classification,
                $fundingAgency,
                $sdgTags,
                $expectedOutput,
                $expectedOutputOther,
                $startDate,
                $endDate,
                $status,
                $approvalStage,
                $isScopus,
                $coauthorEmails,
                $coauthorCanEdit,
                $rowNumber
            ) {
                $research = Research::query()->create([
                    'reference_number' => $referenceNumber,
                    'registration_type' => $registrationType,
                    'title' => $title,
                    'primary_author_id' => $author->id,
                    'mother_college_id' => $motherCollege->id,
                    'other_college_id' => $otherCollegeIds === [] ? null : $otherCollegeIds,
                    'research_classification' => $classification,
                    'funding_agency' => $fundingAgency,
                    'sdg_tags' => $sdgTags,
                    'expected_output' => $expectedOutput,
                    'expected_output_other' => $expectedOutputOther,
                    'start_date' => $startDate,
                    'estimated_completion_date' => $endDate,
                    'status' => $status,
                    'approval_stage' => $approvalStage,
                    'submitted_at' => now(),
                    'revision_count' => 0,
                    'is_scopus_indexed' => $isScopus,
                ]);

                ResearchAuthor::query()->create([
                    'research_id' => $research->id,
                    'user_id' => $author->id,
                    'employee_number' => $author->employee_number,
                    'name' => $author->name,
                    'first_name' => $author->first_name,
                    'last_name' => $author->last_name,
                    'middle_name' => $author->middle_name,
                    'suffix' => $author->suffix,
                    'college_id' => $author->college_id,
                    'email' => $author->email,
                    'is_primary' => true,
                    'can_edit' => true,
                ]);

                foreach ($coauthorEmails as $index => $rawEmail) {
                    $email = strtolower(trim($rawEmail));
                    if ($email === '') {
                        continue;
                    }

                    $coAuthor = User::query()->where('email', $email)->first();
                    if ($coAuthor === null) {
                        $this->skip(
                            $rowNumber,
                            $email,
                            'Co-author email not found: '.$email
                        );

                        continue;
                    }

                    ResearchAuthor::query()->create([
                        'research_id' => $research->id,
                        'user_id' => $coAuthor->id,
                        'author_type' => 'internal',
                        'employee_number' => $coAuthor->employee_number,
                        'name' => $coAuthor->name,
                        'first_name' => $coAuthor->first_name,
                        'last_name' => $coAuthor->last_name,
                        'middle_name' => $coAuthor->middle_name,
                        'suffix' => $coAuthor->suffix,
                        'college_id' => $coAuthor->college_id,
                        'email' => $coAuthor->email,
                        'is_primary' => false,
                        'can_edit' => (bool) (int) ($coauthorCanEdit[$index] ?? 1),
                    ]);
                }

                return $research;
            });

            // Clear OVPRI / admin caches
            Cache::forget('ovpri_stats_all_'.now()->format('Y-m-d-H'));
            for ($year = now()->year - 9; $year <= now()->year + 1; $year++) {
                Cache::forget('ovpri_stats_'.$year.'_'.now()->format('Y-m-d-H'));
            }
            Cache::forget('admin_monthly_stats_'.now()->format('Y-m'));
            Cache::forget('sdg_counts');
            Cache::forget('sdg_counts_all');
            for ($year = now()->year - 9; $year <= now()->year + 1; $year++) {
                Cache::forget('sdg_counts_'.$year);
            }

            // Clear dean cache for the research college
            $collegeId = $research->mother_college_id;
            $deanUsers = User::whereHas('roles', fn ($q) => $q->where('name', 'college_dean'))
                ->where('college_id', $collegeId)
                ->pluck('id');
            foreach ($deanUsers as $deanId) {
                Cache::forget('dean_stats_'.$deanId.'_all_'.now()->format('Y-m-d'));
                for ($year = now()->year - 9; $year <= now()->year + 1; $year++) {
                    Cache::forget('dean_stats_'.$deanId.'_'.$year.'_'.now()->format('Y-m-d'));
                }
            }

            $this->imported++;
        } catch (\Throwable $e) {
            $value = trim((string) ($data['title'] ?? ''));
            if ($value === '') {
                $value = strtolower(trim((string) ($data['primary_author_email'] ?? '')));
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

    private function nullableUpper(mixed $value): ?string
    {
        $trimmed = strtoupper(trim((string) ($value ?? '')));

        if ($trimmed === '' || in_array($trimmed, ['NA', 'N/A', 'NONE', '-'], true)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * @return list<int>
     */
    private function parseSdgTags(mixed $value): array
    {
        $parts = $this->parsePipeList($value);
        $tags = [];

        foreach ($parts as $part) {
            if (! is_numeric($part)) {
                continue;
            }

            $n = (int) $part;
            if ($n >= 1 && $n <= 17) {
                $tags[] = $n;
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @return list<string>
     */
    private function parsePipeList(mixed $value): array
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            explode('|', $raw)
        ), static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return list<int>
     */
    private function parseOtherCollegeIds(mixed $value, int $motherCollegeId): array
    {
        $ids = [];

        foreach ($this->parsePipeList($value) as $code) {
            $college = College::query()->where('code', strtoupper($code))->first();
            if ($college === null || (int) $college->id === $motherCollegeId) {
                continue;
            }

            $ids[] = (int) $college->id;
        }

        return array_values(array_unique($ids));
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            }

            return Carbon::parse(trim((string) $value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateReferenceNumber(int $year, College $college): string
    {
        $prefix = 'AUF-'.$year.'-'.$college->code.'-';

        return DB::transaction(function () use ($prefix) {
            $last = Research::withTrashed()
                ->where('reference_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('reference_number')
                ->value('reference_number');

            $next = 1;
            if ($last !== null && preg_match('/(\d{4})$/', $last, $matches)) {
                $next = (int) $matches[1] + 1;
            } else {
                $next = Research::withTrashed()
                    ->where('reference_number', 'like', $prefix.'%')
                    ->count() + 1;
            }

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
