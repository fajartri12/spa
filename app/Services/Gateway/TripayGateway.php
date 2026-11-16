<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Enums\GatewayProvider;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver Tripay (Closed Payment).
 *
 * Catatan implementasi: nominal pada Tripay bersifat integer rupiah, sehingga
 * nilai desimal dibulatkan. Tanda tangan callback dihitung dari body mentah
 * dengan private key; bila penyedia mengubah skema tanda tangan, hanya kelas
 * ini yang perlu disesuaikan.
 */
final class TripayGateway implements PaymentGateway
{
    public function provider(): string
    {
        return GatewayProvider::Tripay->value;
    }

    public function isConfigured(): bool
    {
        $config = $this->config();

        return (bool) ($config['enabled'] ?? false)
            && filled($config['api_key'] ?? null)
            && filled($config['private_key'] ?? null)
            && filled($config['merchant_code'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    public function channels(): array
    {
        /** @var array<string, string> $channels */
        $channels = $this->config()['channels'] ?? [];

        return $channels;
    }

    public function createCharge(PaymentIntent $intent, array $options = []): GatewayCharge
    {
        if (! $this->isConfigured()) {
            throw new GatewayException('Kredensial Tripay belum lengkap.');
        }

        $config = $this->config();
        $method = (string) ($options['channel'] ?? $intent->channel ?? 'BRIVA');
        $amount = (int) round((float) $intent->amount);
        $student = $intent->student;
        $bill = $intent->studentBill;
        $expiryHours = (int) config('payment_gateway.expiry_hours', 24);

        $payload = [
            'method' => $method,
            'merchant_ref' => $intent->reference,
            'amount' => $amount,
            'customer_name' => $student?->name ?? 'Siswa',
            'customer_email' => $student?->email ?: 'noreply@example.sch.id',
            'customer_phone' => $student?->phone ?: '0800000000',
            'order_items' => [
                [
                    'sku' => (string) ($bill?->bill_number ?? $intent->reference),
                    'name' => (string) ($bill?->bill?->title ?? 'Tagihan sekolah'),
                    'price' => $amount,
                    'quantity' => 1,
                ],
            ],
            'callback_url' => $config['callback_url'] ?: route('webhooks.payment.tripay'),
            'return_url' => $options['return_url'] ?? url('/portal/siswa'),
            'expired_time' => now()->addHours($expiryHours)->getTimestamp(),
            'signature' => hash_hmac(
                'sha256',
                ($config['merchant_code'] ?? '').$intent->reference.$amount,
                (string) $config['private_key'],
            ),
        ];

        try {
            $response = Http::withToken((string) $config['api_key'])
                ->acceptJson()
                ->timeout(20)
                ->post(rtrim((string) $config['base_url'], '/').'/transaction/create', $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Tripay tidak dapat dihubungi.', ['reference' => $intent->reference]);

            throw new GatewayException('Penyedia pembayaran tidak dapat dihubungi. Coba lagi beberapa saat lagi.', 0, $exception);
        }

        if (! $response->successful() || (bool) $response->json('success') === false) {
            Log::warning('Tripay menolak pembuatan transaksi.', [
                'reference' => $intent->reference,
                'status' => $response->status(),
                'message' => $response->json('message'),
            ]);

            throw new GatewayException((string) ($response->json('message') ?: 'Penyedia pembayaran menolak permintaan.'));
        }

        /** @var array<string, mixed> $data */
        $data = (array) $response->json('data', []);

        return new GatewayCharge(
            provider: GatewayProvider::Tripay,
            reference: $intent->reference,
            payCode: isset($data['pay_code']) ? (string) $data['pay_code'] : null,
            qrUrl: isset($data['qr_url']) ? (string) $data['qr_url'] : null,
            qrString: isset($data['qr_string']) ? (string) $data['qr_string'] : null,
            providerReference: isset($data['reference']) ? (string) $data['reference'] : null,
            expiresAt: isset($data['expired_time']) ? Carbon::createFromTimestamp((int) $data['expired_time'])->toDateTimeString() : null,
            fee: (float) ($data['fee_customer'] ?? $data['total_fee'] ?? 0),
            raw: $data,
        );
    }

    public function verifySignature(Request $request): bool
    {
        $signature = (string) $request->header('X-Callback-Signature', '');
        $privateKey = (string) ($this->config()['private_key'] ?? '');

        if ($signature === '' || $privateKey === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $privateKey);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): GatewayWebhookResult
    {
        $reference = (string) $request->input('merchant_ref', '');

        if ($reference === '') {
            throw new GatewayException('Notifikasi tidak memuat merchant_ref.');
        }

        // Tripay mengirim status pada `status` untuk pembayaran dan
        // `status_refund`/`status_failed` untuk kasus lain.
        $status = strtoupper((string) $request->input('status', ''));
        $mapped = match ($status) {
            'PAID' => PaymentIntentStatus::Paid,
            'EXPIRED' => PaymentIntentStatus::Expired,
            'FAILED' => PaymentIntentStatus::Failed,
            'REFUND' => PaymentIntentStatus::Cancelled,
            'UNPAID' => PaymentIntentStatus::Pending,
            default => PaymentIntentStatus::Pending,
        };

        return new GatewayWebhookResult(
            provider: GatewayProvider::Tripay,
            reference: $reference,
            status: $mapped,
            amount: (float) $request->input('amount', 0),
            paidAt: $request->input('paid_at') ? Carbon::createFromTimestamp((int) $request->input('paid_at'))->toDateTimeString() : null,
            providerReference: $request->input('reference') !== null ? (string) $request->input('reference') : null,
            fee: (float) $request->input('fee_merchant', 0),
            raw: $request->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('payment_gateway.providers.tripay', []);

        return $config;
    }
}
