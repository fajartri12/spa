<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\GatewayProvider;
use App\Http\Controllers\Controller;
use App\Services\Gateway\GatewayException;
use App\Services\Gateway\GatewayManager;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Penerima notifikasi pembayaran dari payment gateway.
 *
 * Endpoint berada di luar middleware `auth` (dipanggil penyedia), sehingga
 * keamanan bertumpu pada verifikasi tanda tangan. Signature tidak valid
 * menghasilkan 403 tanpa memproses apa pun. Selalu membalas 200 untuk notifikasi
 * valid agar penyedia tidak mengulang tanpa henti.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        GatewayManager $gateways,
        PaymentGatewayService $service,
    ): JsonResponse {
        $enum = GatewayProvider::tryFrom($provider);

        if ($enum === null) {
            abort(404);
        }

        $gateway = $gateways->driver($enum);

        if (! $gateway->verifySignature($request)) {
            Log::warning('Webhook payment gateway ditolak: tanda tangan tidak valid.', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Tanda tangan tidak valid.'], 403);
        }

        try {
            $result = $gateway->parseWebhook($request);
        } catch (GatewayException $exception) {
            // Payload rusak: balas 200 agar penyedia tidak mengulang selamanya,
            // tetapi catat untuk investigasi.
            Log::warning('Webhook payment gateway tidak dapat dibaca.', [
                'provider' => $provider,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Notifikasi diabaikan.'], 200);
        }

        try {
            $intent = $service->handleWebhook($enum, $result);
        } catch (GatewayException $exception) {
            Log::warning('Webhook payment gateway menunjuk referensi tak dikenal.', [
                'provider' => $provider,
                'reference' => $result->reference,
            ]);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Notifikasi diterima.',
            'reference' => $intent->reference,
            'status' => $intent->status->value,
        ]);
    }
}
