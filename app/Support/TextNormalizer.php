<?php

namespace App\Support;

/**
 * Normalizes user-entered text for storage as uppercase (UTF-8 safe).
 * Mirrors strtoupper() behavior for display consistency; uses mb_strtoupper for multibyte strings.
 */
final class TextNormalizer
{
    public static function upper(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $trimmed = trim($value);

        return $trimmed === '' ? '' : mb_strtoupper($trimmed, 'UTF-8');
    }

    public static function upperNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return mb_strtoupper($trimmed, 'UTF-8');
    }
}
