<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Research;
use App\Models\User;
use App\Services\FileValidationService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private FileValidationService $fileValidation
    ) {}

    public function store(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('update', $research);

        if ($request->filled('external_link')) {
            $request->validate(['external_link' => ['required', 'url', 'max:2048']]);

            Document::create([
                'research_id' => $research->id,
                'uploaded_by' => auth()->id(),
                'original_filename' => $request->input('external_link'),
                'stored_filename' => null,
                'disk_path' => null,
                'external_link' => $request->input('external_link'),
                'mime_type' => 'text/uri-list',
                'file_size_bytes' => 0,
                'research_status_at_upload' => $research->status,
                'version' => ((int) $research->documents()->max('version')) + 1,
            ]);

            return back()->with('success', __('Document saved successfully.'));
        }

        $fileKey = $request->hasFile('files') ? 'files' : ($request->hasFile('documents') ? 'documents' : null);

        if ($fileKey === null) {
            return back()
                ->withErrors(['files' => __('Please choose file(s) or paste a link.')]);
        }

        $request->validate([
            $fileKey => ['required', 'array', 'max:2'],
            $fileKey.'.*' => ['file', 'max:102400', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ]);

        foreach ($request->file($fileKey) as $index => $file) {
            $this->persistUploadedFile($research, $request->user(), $file, $fileKey.'.'.$index);
        }

        return back()->with('success', __('Document saved successfully.'));
    }

    public function destroy(Document $document): RedirectResponse
    {
        $research = $document->research;
        $this->authorize('update', $research);

        abort_if($research->approval_stage !== 'draft', 403, __('Cannot delete documents after submission.'));
        abort_if((int) $document->uploaded_by !== (int) auth()->id(), 403);

        if ($document->disk_path && $this->researchAppDisk()->exists($document->disk_path)) {
            $this->researchAppDisk()->delete($document->disk_path);
        }
        $document->delete();

        return back()->with('success', __('Document deleted.'));
    }

    private function persistUploadedFile(Research $research, User $user, UploadedFile $file, string $attribute): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $this->fileValidation->validateMime($file, $extension, $attribute);

        $collegeId = (int) $research->mother_college_id;
        $uuid = (string) Str::uuid();
        $storedBasename = $uuid.'.'.$extension;
        $relativePath = 'research_files/'.$collegeId.'/'.$research->id.'/'.$storedBasename;

        $disk = $this->researchAppDisk();
        $disk->put($relativePath, $file->get());

        try {
            Document::create([
                'research_id' => $research->id,
                'uploaded_by' => (int) $user->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename' => $storedBasename,
                'disk_path' => $relativePath,
                'external_link' => null,
                'mime_type' => $mimeType,
                'file_size_bytes' => $file->getSize(),
                'research_status_at_upload' => $research->status,
                'version' => ((int) $research->documents()->max('version')) + 1,
            ]);
        } catch (\Throwable $e) {
            $disk->delete($relativePath);
            throw $e;
        }
    }

    private function researchAppDisk(): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => true,
        ]);
    }
}
