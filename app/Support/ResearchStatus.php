<?php

namespace App\Support;

final class ResearchStatus
{
    public const DRAFT = 'draft';

    public const INITIAL_DEAN_REVIEW = 'initial_dean_review';

    public const INITIAL_OVPRI_REVIEW = 'initial_ovpri_review';

    public const INITIAL_REJECTED = 'initial_rejected';

    public const RESEARCH_REGISTERED = 'research_registered';

    public const RESEARCH_COMPLETED = 'research_completed';

    public const FINAL_DEAN_REVIEW = 'final_dean_review';

    public const FINAL_OVPRI_REVIEW = 'final_ovpri_review';

    public const FINAL_REJECTED = 'final_rejected';

    public const RESEARCH_ACCEPTED = 'research_accepted';

    public const REVIEW_CYCLE_INITIAL = 'initial';

    public const REVIEW_CYCLE_FINAL = 'final';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return config('kmsar.statuses', []);
    }

    /**
     * @return list<string>
     */
    public static function initialReviewStatuses(): array
    {
        return config('kmsar.initial_review_statuses', []);
    }

    /**
     * @return list<string>
     */
    public static function finalReviewStatuses(): array
    {
        return config('kmsar.final_review_statuses', []);
    }

    public static function reviewCycle(?string $status): ?string
    {
        return match ($status) {
            self::INITIAL_DEAN_REVIEW,
            self::INITIAL_OVPRI_REVIEW,
            self::INITIAL_REJECTED => self::REVIEW_CYCLE_INITIAL,
            self::RESEARCH_COMPLETED,
            self::FINAL_DEAN_REVIEW,
            self::FINAL_OVPRI_REVIEW,
            self::FINAL_REJECTED => self::REVIEW_CYCLE_FINAL,
            default => null,
        };
    }

    public static function isDeanQueueStatus(string $status): bool
    {
        return in_array($status, [self::INITIAL_DEAN_REVIEW, self::FINAL_DEAN_REVIEW], true);
    }

    public static function isOvpriQueueStatus(string $status): bool
    {
        return in_array($status, [self::INITIAL_OVPRI_REVIEW, self::FINAL_OVPRI_REVIEW], true);
    }

    /**
     * Pre-submission statuses visible only to faculty on My Research.
     *
     * @return list<string>
     */
    public static function facultyOnlyStatuses(): array
    {
        return [self::DRAFT];
    }

    /**
     * Workflow status options for faculty filters (draft is shown on cards, not in the dropdown).
     *
     * @return array<string, string>
     */
    public static function facultyFilterOptions(): array
    {
        return self::workflowFilterOptions();
    }

    /**
     * Workflow status options for dashboard and report filters (excludes internal/transient statuses).
     *
     * @return array<string, string>
     */
    public static function workflowFilterOptions(): array
    {
        return collect(self::institutionalFilterOptions())
            ->reject(fn (string $label, string $value) => $value === self::RESEARCH_COMPLETED)
            ->all();
    }

    /**
     * Statuses shown in dean, OVPRI, admin, and CDAIC filters and registers.
     *
     * @return list<string>
     */
    public static function institutionalStatuses(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $status) => ! in_array($status, self::facultyOnlyStatuses(), true),
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function institutionalFilterOptions(): array
    {
        return collect(self::institutionalStatuses())
            ->mapWithKeys(fn (string $value) => [$value => self::label($value)])
            ->all();
    }

    /**
     * In-progress counts for institutional dashboards: research registered only.
     *
     * @return list<string>
     */
    public static function institutionalInProgressStatuses(): array
    {
        return [self::RESEARCH_REGISTERED];
    }

    /**
     * Outcome-based options for the reports "Research progress" filter (excludes workflow statuses).
     *
     * @return array<string, string>
     */
    public static function reportProgressFilterOptions(): array
    {
        return \App\Models\OutcomeClassification::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'code')
            ->all();
    }

    public static function isFacultyOnly(string $status): bool
    {
        return in_array($status, self::facultyOnlyStatuses(), true);
    }

    /**
     * Statuses that must not be used in workflow filter query params (dropdown or URL).
     */
    public static function isBlockedWorkflowFilter(?string $status): bool
    {
        if ($status === null || $status === '') {
            return false;
        }

        return self::isFacultyOnly($status)
            || $status === 'proposal'
            || $status === self::RESEARCH_COMPLETED;
    }

    public static function isPreSubmission(string $status): bool
    {
        return self::isFacultyOnly($status);
    }

    public static function isFullyEditable(string $status): bool
    {
        return in_array($status, [self::DRAFT, self::INITIAL_REJECTED], true);
    }

    public static function isDocumentsEditable(string $status): bool
    {
        return in_array($status, [
            self::RESEARCH_REGISTERED,
            self::RESEARCH_ACCEPTED,
            self::FINAL_REJECTED,
        ], true);
    }

    /**
     * Faculty may add or remove uploaded files (not registration wizard fields).
     */
    public static function canModifyDocuments(string $status): bool
    {
        return self::isFullyEditable($status) || self::isDocumentsEditable($status);
    }

    /**
     * Documents wizard opens in registration-locked mode (upload/delete only).
     */
    public static function usesDocumentsOnlyUploadPage(string $status): bool
    {
        return self::isDocumentsEditable($status) && ! self::isFullyEditable($status);
    }

    /**
     * Show the standalone “Manage documents” action on the research record page.
     *
     * Not shown for initial review returns — faculty use Edit & resubmit (full wizard).
     */
    public static function showsManageDocumentsAction(string $status): bool
    {
        return in_array($status, [
            self::RESEARCH_REGISTERED,
            self::RESEARCH_ACCEPTED,
            self::FINAL_REJECTED,
        ], true);
    }

    public static function isOutcomeEditable(string $status): bool
    {
        return in_array($status, [
            self::RESEARCH_REGISTERED,
            self::RESEARCH_ACCEPTED,
            self::FINAL_REJECTED,
        ], true);
    }

    public static function isRegistrationLocked(string $status): bool
    {
        return ! self::isFullyEditable($status);
    }

    public static function canSubmitIntake(string $status, string $registrationType): bool
    {
        if (! self::isPreSubmission($status)) {
            return false;
        }

        return in_array($registrationType, config('kmsar.registration_types', []), true);
    }

    public static function canResubmitInitial(string $status): bool
    {
        return $status === self::INITIAL_REJECTED;
    }

    public static function canResubmitFinal(string $status): bool
    {
        return $status === self::FINAL_REJECTED;
    }

    public static function canSubmitCompletion(string $status): bool
    {
        return in_array($status, [self::RESEARCH_REGISTERED, self::RESEARCH_ACCEPTED], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => [__('Invalid status transition from :from to :to.', [
                    'from' => $from,
                    'to' => $to,
                ])],
            ]);
        }
    }

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = self::transitions()[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function transitions(): array
    {
        return [
            self::DRAFT => [
                self::INITIAL_DEAN_REVIEW,
                self::RESEARCH_REGISTERED,
            ],
            self::INITIAL_DEAN_REVIEW => [
                self::INITIAL_OVPRI_REVIEW,
                self::INITIAL_REJECTED,
            ],
            self::INITIAL_OVPRI_REVIEW => [
                self::RESEARCH_REGISTERED,
                self::INITIAL_REJECTED,
            ],
            self::INITIAL_REJECTED => [
                self::INITIAL_DEAN_REVIEW,
            ],
            self::RESEARCH_REGISTERED => [
                self::RESEARCH_COMPLETED,
            ],
            self::RESEARCH_COMPLETED => [
                self::FINAL_DEAN_REVIEW,
            ],
            self::FINAL_DEAN_REVIEW => [
                self::FINAL_OVPRI_REVIEW,
                self::FINAL_REJECTED,
            ],
            self::FINAL_OVPRI_REVIEW => [
                self::RESEARCH_ACCEPTED,
                self::FINAL_REJECTED,
            ],
            self::FINAL_REJECTED => [
                self::RESEARCH_COMPLETED,
            ],
            self::RESEARCH_ACCEPTED => [
                self::RESEARCH_COMPLETED,
            ],
        ];
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            self::DRAFT => __('Draft'),
            'proposal' => __('Draft'),
            self::INITIAL_DEAN_REVIEW => __('Initial Dean Review'),
            self::INITIAL_OVPRI_REVIEW => __('Initial OVPRI Review'),
            self::INITIAL_REJECTED => __('Initial Review Returned'),
            self::RESEARCH_REGISTERED => __('Research Registered'),
            self::RESEARCH_COMPLETED => __('Research Completed'),
            self::FINAL_DEAN_REVIEW => __('Final Dean Review'),
            self::FINAL_OVPRI_REVIEW => __('Final OVPRI Review'),
            self::FINAL_REJECTED => __('Final Review Returned'),
            self::RESEARCH_ACCEPTED => __('Research Accepted'),
            default => $status ? ucwords(str_replace('_', ' ', $status)) : '-',
        };
    }
}
