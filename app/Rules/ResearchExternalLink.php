<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ResearchExternalLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = self::normalize(is_string($value) ? $value : '');

        if ($normalized === null) {
            return;
        }

        if (! self::isValidUrl($normalized)) {
            $fail(__('Invalid link. Please enter a correct URL.'));

            return;
        }

        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));

        foreach (config('kmsar.disallowed_external_link_hosts', []) as $blocked) {
            $blocked = strtolower((string) $blocked);
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                $fail(__('This link type is not accepted for supporting proof. Please use a Google Drive, OneDrive, Dropbox, or DOI link instead.'));

                return;
            }
        }
    }

    public static function isValidUrl(string $normalized): bool
    {
        if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        // Reject bare words like "abc" → "https://abc" — must be a real domain (e.g. drive.google.com).
        if (! str_contains($host, '.')) {
            return false;
        }

        return (bool) preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
            $host
        );
    }

    public static function normalize(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        return $value;
    }

    /**
     * @param  list<string|null>  $links
     * @return list<string>
     */
    public static function normalizeList(array $links): array
    {
        return collect($links)
            ->map(fn ($link) => self::normalize(is_string($link) ? $link : ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
