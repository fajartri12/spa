<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Enums\GatewayProvider;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Driver simulator untuk pengujian dan demo tanpa kredensial.
 *
 * Tanda tangan berupa HMAC dari `reference` dengan `app.key` sehingga webhook
 * tetap harus dibangkitkan oleh aplikasi, bukan diketik manual. Driver ini
 * tidak pernah dipilih otomatis: harus diaktifkan lewat konfigurasi.
 */
final class FakeGateway implements PaymentGateway
{
    public function provider(): string
    {
        return GatewayProvider::Fake->value;
    }

    public function isConfigured(): bool
    {
        return (bool) config('payment_gateway.providers.fake.enabled', false);
    }

    /**
     * @return array<string, string>
     */
    public function channels(): array
    {
        /** @var array<string, string> $channels */
        $channels = config('payment_gateway.providers.fake.channels', []);

        return $channels;
    }

    public function createCharge(PaymentIntent $intent, array $options = []): GatewayCharge
    {
        if (! $this->isConfigured()) {
            throw new GatewayException('Simulator pembayaran tidak diaktifkan.');
        }

        $channel = (string) ($options['channel'] ?? $intent->channel ?? 'va');
        $isQris = $channel === 'qris';

        return new GatewayCharge(
            provider: GatewayProvider::Fake,
            reference: $intent->reference,
            payCode: $isQris ? null : '8808'.str_pad((string) $intent->id, 10, '0', STR_PAD_LEFT),
            qrUrl: $isQris ? url('/simulator/qris/'.$intent->reference) : null,
            qrString: $isQris ? 'FAKE-QRIS-'.$intent->reference : null,
            providerReference: 'SIM-'.$intent->reference,
            expiresAt: now()->addHours((int) config('payment_gateway.expiry_hours', 24))->toDateTimeString(),
            fee: 0.0,
            raw: ['simulated' => true, 'channel' => $channel],
        );
    }

    public function verifySignature(Request $request): bool
    {
        $signature = (string) $request->header('X-Simulator-Signature', '');
        $reference = (string) $request->input('reference', '');

        if ($signature === '' || $reference === '') {
            return false;
        }

        return hash_equals($this->sign($reference), $signature);
    }

    /**
     * Tanda tangan untuk membangkitkan notifikasi pada pengujian.
     */
    public function sign(string $reference): string
    {
        return hash_hmac('sha256', $reference, (string) config('app.key'));
    }

    public function parseWebhook(Request $request): GatewayWebhookResult
    {
        $reference = (string) $request->input('reference', '');

        if ($reference === '') {
            throw new GatewayException('Notifikasi simulator tidak memuat reference.');
        }

        $status = PaymentIntentStatus::tryFrom((string) $request->input('status', 'paid'))
            ?? PaymentIntentStatus::Paid;

        return new GatewayWebhookResult(
            provider: GatewayProvider::Fake,
            reference: $reference,
            status: $status,
            amount: (float) $request->input('amount', 0),
            paidAt: $request->input('paid_at') ?: Carbon::now()->toDateTimeString(),
            providerReference: $request->input('provider_reference') !== null
                ? (string) $request->input('provider_reference')
                : 'SIM-'.$reference,
            raw: $request->all(),
        );
    }

    /**
     * Referensi unik untuk simulasi, memakai awalan yang sama dengan intent.
     */
    public static function makeReference(): string
    {
        return 'SIM-'.Str::ulid();
    }
}
