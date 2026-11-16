<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function __invoke(Request $request, PaymentProof $proof): StreamedResponse
    {
        // Endpoint terotorisasi: hanya pengguna dengan izin lihat pembayaran yang dapat mengunduh.
        abort_unless($request->user()?->can('payments.view'), 403);
        abort_unless(Storage::disk('local')->exists($proof->file_path), 404);

        return Storage::disk('local')->download($proof->file_path, $proof->original_name ?? 'bukti-pembayaran');
    }
}
