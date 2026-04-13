<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class FileValidationService
{
    /**
     * Extension (lowercase) => expected MIME from magic-byte detection (finfo).
     *
     * @var array<string, string>
     */
    public const ALLOWED_MIMES = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /**
     * MIME aliases finfo may return that are acceptable for a given extension.
     *
     * @var array<string, list<string>>
     */
    private const MIME_ALIASES = [
        'jpg' => ['image/jpg'],
        'jpeg' => ['image/jpg'],
    ];

    /**
     * Validate uploaded file content using PHP fileinfo (magic bytes), not client-provided type.
     *
     * @return string Detected MIME type (normalized to primary expected type when alias matched)
     */
    public function validateMime(UploadedFile $file, string $extension, string $attribute = 'document'): string
    {
        $extension = strtolower($extension);

        if (! isset(self::ALLOWED_MIMES[$extension])) {
            throw ValidationException::withMessages([
                $attribute => [__('This file extension is not allowed.')],
            ]);
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages([
                $attribute => [__('The uploaded file could not be read.')],
            ]);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw ValidationException::withMessages([
                $attribute => [__('Could not inspect file type.')],
            ]);
        }

        try {
            $detected = finfo_file($finfo, $path);
        } finally {
            finfo_close($finfo);
        }

        if ($detected === false) {
            throw ValidationException::withMessages([
                $attribute => [__('Could not determine file type from contents.')],
            ]);
        }

        $expected = self::ALLOWED_MIMES[$extension];

        if ($detected === $expected) {
            return $expected;
        }

        $aliases = self::MIME_ALIASES[$extension] ?? [];
        if (in_array($detected, $aliases, true) && $expected === 'image/jpeg') {
            return $expected;
        }

        // OOXML formats are ZIP packages; some libmagic builds report application/zip.
        if (in_array($extension, ['docx', 'xlsx'], true) && $detected === 'application/zip') {
            return $expected;
        }

        throw ValidationException::withMessages([
            $attribute => [__('The file type does not match its contents or is not allowed.')],
        ]);
    }
}
