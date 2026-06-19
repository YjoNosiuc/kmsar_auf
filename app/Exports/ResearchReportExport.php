<?php

namespace App\Exports;

use App\Models\Research;
use App\Services\ReportGeneratorService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ResearchReportExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    /**
     * @param  Collection<int, Research>  $researches
     */
    public function __construct(
        private Collection $researches,
        private ReportGeneratorService $service,
        private bool $collegeReport = false
    ) {}

    public function collection(): Collection
    {
        return $this->researches->map(fn (Research $r) => $this->rowFor($r));
    }

    /**
     * @return list<string>
     */
    public function rowFor(Research $r): array
    {
        return $this->mapRow($r);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        if ($this->collegeReport) {
            return [
                __('Faculty'),
                __("Author's Name"),
                __('Co-Authors'),
                __('Registration Type'),
                __('Title of Research'),
                __('Research Progress'),
            ];
        }

        return [
            __("Author's Name"),
            __('College'),
            __('Other College/Unit Affiliations'),
            __('Co-Authors'),
            __('Title of Research'),
            __('Registration Type'),
            __('Classification'),
            __('Research Progress'),
        ];
    }

    /**
     * @return list<string>
     */
    protected function mapRow(Research $r): array
    {
        $authorName = (string) ($r->primaryAuthor?->name ?? '—');

        if ($this->collegeReport) {
            $pa = $r->primaryAuthor;
            $facultyColumn = $pa
                ? trim(($pa->employee_number ? $pa->employee_number.' — ' : '').$pa->name)
                : '—';

            return [
                $facultyColumn,
                $authorName,
                $this->service->coAuthorsCommaSeparated($r),
                $this->service->registrationTypeLabel($r->registration_type),
                $r->title,
                $this->service->statusLabel($r->status),
            ];
        }

        $secondColumn = $r->motherCollege
            ? trim(($r->motherCollege->code ?? '').' — '.($r->motherCollege->name ?? ''))
            : '—';

        return [
            $authorName,
            $secondColumn,
            $this->service->otherCollegeAffiliations($r),
            $this->service->coAuthorsLine($r),
            $r->title,
            $this->service->registrationTypeLabel($r->registration_type),
            $this->service->classificationLabel($r->research_classification),
            $this->service->statusLabel($r->status),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
