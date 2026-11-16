<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicantDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicantDocumentController extends Controller
{
    public function __invoke(Request $request, ApplicantDocument $document): StreamedResponse
    {
        // Dokumen sensitif: hanya pengguna ber-izin PPDB yang boleh mengunduh.
        abort_unless($request->user()?->can('admissions.view'), 403);

        $disk = $document->disk === 'private' ? 'local' : $document->disk;

        abort_unless(Storage::disk($disk)->exists($document->storage_path), 404);

        return Storage::disk($disk)->download($document->storage_path, $document->original_name);
    }
}
