<?php

namespace App\Support;

final class ResearchStatus
{
    public const PROPOSAL = 'proposal';

    public const INITIAL_DEAN_REVIEW = 'initial_dean_review';

    public const INITIAL_OVPRI_REVIEW = 'initial_ovpri_review';

    public const INITIAL_REJECTED = 'initial_rejected';

    public const RESEARCH_REGISTERED = 'research_registered';

    public const ONGOING = 'ongoing';

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

    public static function isFullyEditable(string $status): bool
    {
        return in_array($status, [self::PROPOSAL, self::INITIAL_REJECTED], true);
    }

    public static function isOutcomeEditable(string $status): bool
    {
        return in_array($status, [
            self::ONGOING,
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
        if ($status !== self::PROPOSAL) {
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
        return in_array($status, [self::ONGOING, self::RESEARCH_ACCEPTED], true);
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
            self::PROPOSAL => [
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
                self::ONGOING,
            ],
            self::ONGOING => [
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
            self::PROPOSAL => __('Proposal'),
            self::INITIAL_DEAN_REVIEW => __('Initial Dean Review'),
            self::INITIAL_OVPRI_REVIEW => __('Initial OVPRI Review'),
            self::INITIAL_REJECTED => __('Initial Review — Returned'),
            self::RESEARCH_REGISTERED => __('Research Registered'),
            self::ONGOING => __('Ongoing'),
            self::RESEARCH_COMPLETED => __('Research Completed'),
            self::FINAL_DEAN_REVIEW => __('Final Dean Review'),
            self::FINAL_OVPRI_REVIEW => __('Final OVPRI Review'),
            self::FINAL_REJECTED => __('Final Review — Returned'),
            self::RESEARCH_ACCEPTED => __('Research Accepted'),
            default => $status ? ucwords(str_replace('_', ' ', $status)) : '—',
        };
    }
}
