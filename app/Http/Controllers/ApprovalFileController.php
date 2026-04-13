<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Research;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalFileController extends Controller
{
    use AuthorizesRequests;

    public function preview(Research $research, Document $document): StreamedResponse
    {
        if ((int) $document->research_id !== (int) $research->id) {
            abort(404);
        }

        $this->authorize('view', $document);

        if ($document->external_link || ! $document->disk_path) {
            abort(404);
        }

        return $this->researchAppDisk()->response(
            $document->disk_path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type, 'Content-Disposition' => 'inline; filename="' . $document->original_filename . '"']
        );
    }

    /**
     * Files live under storage/app/research_files/... (never storage/app/public).
     */
    private function researchAppDisk(): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => true,
        ]);
    }
}
