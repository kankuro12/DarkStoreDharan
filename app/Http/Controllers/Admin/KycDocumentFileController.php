<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KycDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams private KYC files to authorised staff only; every view is audited. */
class KycDocumentFileController extends Controller
{
    public function __invoke(KycDocument $document, string $side): StreamedResponse
    {
        $path = $side === 'back' ? $document->file_back : $document->file_front;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        AuditLog::record('kyc_document_viewed', $document, null, ['side' => $side]);

        return Storage::disk('local')->response($path);
    }
}
