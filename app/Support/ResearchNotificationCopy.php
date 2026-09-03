<?php

namespace App\Support;

use App\Models\Research;

/**
 * Cycle-aware copy for research lifecycle in-app and mail notifications.
 */
final class ResearchNotificationCopy
{
    public static function submissionConfirmed(Research $research): string
    {
        $ref = $research->reference_number;

        if ($research->registration_type === 'existing') {
            return __('Your existing research :ref has been registered in KMSAR.', ['ref' => $ref]);
        }

        return __('Your research :ref has been submitted for initial dean review.', ['ref' => $ref]);
    }

    public static function submittedToDean(Research $research): string
    {
        $author = $research->primaryAuthor?->first_name
            ?? $research->primaryAuthor?->name
            ?? __('a faculty member');

        return __('A new research :ref has been submitted for your review by :author.', [
            'ref' => $research->reference_number,
            'author' => $author,
        ]);
    }

    public static function resubmittedToDean(Research $research): string
    {
        $author = $research->primaryAuthor?->first_name
            ?? $research->primaryAuthor?->name
            ?? __('a faculty member');

        $cycle = ResearchStatus::reviewCycle($research->status);

        if ($cycle === ResearchStatus::REVIEW_CYCLE_FINAL) {
            return __('Research :ref outcome submission has been resubmitted for final dean review by :author.', [
                'ref' => $research->reference_number,
                'author' => $author,
            ]);
        }

        return __('Research :ref has been resubmitted for initial dean review by :author.', [
            'ref' => $research->reference_number,
            'author' => $author,
        ]);
    }

    public static function completionSubmittedToDean(Research $research): string
    {
        return __('Research :ref completion has been submitted for final dean review.', [
            'ref' => $research->reference_number,
        ]);
    }

    public static function endorsedFaculty(Research $research): string
    {
        $ref = $research->reference_number;
        $cycle = ResearchStatus::reviewCycle($research->status);

        if ($cycle === ResearchStatus::REVIEW_CYCLE_FINAL) {
            return __('Your research :ref has been endorsed by the college dean for final OVPRI review.', ['ref' => $ref]);
        }

        return __('Your research :ref has been endorsed by the college dean.', ['ref' => $ref]);
    }

    public static function endorsedToOvpri(Research $research): string
    {
        $ref = $research->reference_number;
        $cycle = ResearchStatus::reviewCycle($research->status);

        if ($cycle === ResearchStatus::REVIEW_CYCLE_FINAL) {
            return __('Research :ref completion has been endorsed and awaits final OVPRI/CDAIC review.', ['ref' => $ref]);
        }

        return __('Research :ref has been endorsed and awaits initial OVPRI/CDAIC review.', ['ref' => $ref]);
    }

    public static function approvedFaculty(Research $research): string
    {
        $ref = $research->reference_number;

        return match ($research->status) {
            ResearchStatus::RESEARCH_REGISTERED => __('Your research :ref has been registered by OVPRI.', ['ref' => $ref]),
            ResearchStatus::RESEARCH_ACCEPTED => __('Your research :ref has been accepted by OVPRI.', ['ref' => $ref]),
            default => __('Your research :ref has been approved by OVPRI.', ['ref' => $ref]),
        };
    }

    public static function approvedDean(Research $research): string
    {
        $ref = $research->reference_number;

        return match ($research->status) {
            ResearchStatus::RESEARCH_REGISTERED => __('Research :ref from your college has been registered by OVPRI.', ['ref' => $ref]),
            ResearchStatus::RESEARCH_ACCEPTED => __('Research :ref from your college has been accepted by OVPRI.', ['ref' => $ref]),
            default => __('Research :ref from your college has been approved by OVPRI.', ['ref' => $ref]),
        };
    }

    public static function returnedFaculty(Research $research, ?string $remarks, string $returnedBy): string
    {
        $ref = $research->reference_number;
        $returner = $returnedBy === 'ovpri' ? __('OVPRI') : __('your college dean');
        $cycle = $research->status === ResearchStatus::FINAL_REJECTED
            ? __('final review')
            : __('initial review');

        $message = __('Your research :ref has been returned by :returner for :cycle revision.', [
            'ref' => $ref,
            'returner' => $returner,
            'cycle' => $cycle,
        ]);

        return $message;
    }

    public static function returnedToDean(Research $research, ?string $remarks): string
    {
        $cycle = $research->status === ResearchStatus::FINAL_REJECTED
            ? __('final review')
            : __('initial review');

        return __('Research :ref has been returned by OVPRI for your :cycle action.', [
            'ref' => $research->reference_number,
            'cycle' => $cycle,
        ]);
    }

    public static function appendRemarks(string $message, ?string $remarks): string
    {
        $trimmed = trim((string) $remarks);

        if ($trimmed === '') {
            return $message;
        }

        return $message.' '.__('Remarks: :remarks', ['remarks' => $trimmed]);
    }
}
