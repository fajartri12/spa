<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Models\PaymentIntent;
use Illuminate\Http\Request;

/**
 * Kontrak penyedia payment gateway.
 *
 * Implementasi tidak boleh menulis ke basis data. Seluruh perubahan status
 * dilakukan oleh `PaymentGatewayService` agar aturan keuangan tetap satu pintu.
 */
interface PaymentGateway
{
    /**
     * Kode penyedia pada enum `GatewayProvider`.
     */
    public function provider(): string;

    /**
     * Apakah kredensial penyedia sudah tersedia.
     */
    public function isConfigured(): bool;

    /**
     * Daftar kanal yang ditawarkan: kode => label.
     *
     * @return array<string, string>
     */
    public function channels(): array;

    /**
     * Terbitkan tagihan digital untuk satu intent.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws GatewayException
     */
    public function createCharge(PaymentIntent $intent, array $options = []): GatewayCharge;

    /**
     * Validasi tanda tangan notifikasi. Wajib dipanggil sebelum `parseWebhook()`.
     */
    public function verifySignature(Request $request): bool;

    /**
     * Normalisasi isi notifikasi menjadi hasil yang seragam.
     *
     * @throws GatewayException
     */
    public function parseWebhook(Request $request): GatewayWebhookResult;
}
