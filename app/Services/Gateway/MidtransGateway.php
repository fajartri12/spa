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
 * Driver Midtrans (Core API charge).
 *
 * Virtual Account memakai `bank_transfer`, QRIS memakai `qris`. Nominal
 * dikirim sebagai integer rupiah karena Midtrans tidak menerima desimal.
 * Tanda tangan notifikasi diverifikasi dengan SHA-512 sesuai skema
 * `order_id + status_code + gross_amount + server_key`.
 */
final class MidtransGateway implements PaymentGateway
{
    public function provider(): string
    {
        return GatewayProvider::Midtrans->value;
    }

    public function isConfigured(): bool
    {
        $config = $this->config();

        return (bool) ($config['enabled'] ?? false) && filled($config['server_key'] ?? null);
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
            throw new GatewayException('Kredensial Midtrans belum lengkap.');
        }

        $config = $this->config();
        $channel = (string) ($options['channel'] ?? $intent->channel ?? 'bri_va');
        $amount = (int) round((float) $intent->amount);
        $student = $intent->student;
        $bill = $intent->studentBill;

        $payload = [
            'transaction_details' => [
                'order_id' => $intent->reference,
                'gross_amount' => $amount,
            ],
            'item_details' => [
                [
                    'id' => (string) ($bill?->bill_number ?? $intent->reference),
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => mb_substr((string) ($bill?->bill?->title ?? 'Tagihan sekolah'), 0, 50),
                ],
            ],
            'customer_details' => [
                'first_name' => mb_substr((string) ($student?->name ?? 'Siswa'), 0, 50),
                'email' => $student?->email ?: null,
                'phone' => $student?->phone ?: null,
            ],
            'custom_expiry' => [
                'expiry_duration' => (int) config('payment_gateway.expiry_hours', 24),
                'unit' => 'hour',
            ],
        ];

        if (str_ends_with($channel, '_va')) {
            $payload['payment_type'] = 'bank_transfer';
            $payload['bank_transfer'] = ['bank' => str_replace('_va', '', $channel)];
        } elseif ($channel === 'qris') {
            $payload['payment_type'] = 'qris';
        } else {
            throw new GatewayException("Kanal Midtrans '{$channel}' tidak didukung.");
        }

        try {
            $response = Http::withBasicAuth((string) $config['server_key'], '')
                ->acceptJson()
                ->timeout(20)
                ->post(rtrim((string) $config['base_url'], '/').'/v2/charge', $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Midtrans tidak dapat dihubungi.', ['reference' => $intent->reference]);

            throw new GatewayException('Penyedia pembayaran tidak dapat dihubungi. Coba lagi beberapa saat lagi.', 0, $exception);
        }

        if (! $response->successful()) {
            Log::warning('Midtrans menolak pembuatan transaksi.', [
                'reference' => $intent->reference,
                'status' => $response->status(),
                'message' => $response->json('status_message'),
            ]);

            throw new GatewayException((string) ($response->json('status_message') ?: 'Penyedia pembayaran menolak permintaan.'));
        }

        /** @var array<string, mixed> $data */
        $data = (array) $response->json();

        $vaNumbers = (array) ($data['va_numbers'] ?? []);
        $payCode = $vaNumbers[0]['va_number'] ?? null;

        return new GatewayCharge(
            provider: GatewayProvider::Midtrans,
            reference: $intent->reference,
            payCode: $payCode !== null ? (string) $payCode : null,
            qrUrl: isset($data['actions']) ? $this->qrActionUrl($data['actions']) : null,
            qrString: isset($data['qr_string']) ? (string) $data['qr_string'] : null,
            providerReference: isset($data['transaction_id']) ? (string) $data['transaction_id'] : null,
            expiresAt: isset($data['expiry_time'])
                ? Carbon::parse((string) $data['expiry_time'], 'Asia/Jakarta')->toDateTimeString()
                : null,
            fee: 0.0,
            raw: $data,
        );
    }

    public function verifySignature(Request $request): bool
    {
        $serverKey = (string) ($this->config()['server_key'] ?? '');
        $signature = (string) $request->input('signature_key', '');

        if ($serverKey === '' || $signature === '') {
            return false;
        }

        $expected = hash('sha512', $request->input('order_id', '')
            .$request->input('status_code', '')
            .$request->input('gross_amount', '')
            .$serverKey);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): GatewayWebhookResult
    {
        $reference = (string) $request->input('order_id', '');

        if ($reference === '') {
            throw new GatewayException('Notifikasi tidak memuat order_id.');
        }

        $transactionStatus = (string) $request->input('transaction_status', '');
        $fraudStatus = (string) $request->input('fraud_status', '');

        $mapped = match ($transactionStatus) {
            'settlement' => PaymentIntentStatus::Paid,
            'capture' => $fraudStatus === 'challenge' ? PaymentIntentStatus::Pending : PaymentIntentStatus::Paid,
            'expire' => PaymentIntentStatus::Expired,
            'cancel' => PaymentIntentStatus::Cancelled,
            'deny', 'failure' => PaymentIntentStatus::Failed,
            default => PaymentIntentStatus::Pending,
        };

        return new GatewayWebhookResult(
            provider: GatewayProvider::Midtrans,
            reference: $reference,
            status: $mapped,
            amount: (float) $request->input('gross_amount', 0),
            paidAt: $request->input('settlement_time'),
            providerReference: $request->input('transaction_id') !== null ? (string) $request->input('transaction_id') : null,
            raw: $request->all(),
        );
    }

    /**
     * Ambil URL gambar QRIS dari daftar aksi respons Midtrans.
     *
     * @param  mixed  $actions
     */
    private function qrActionUrl(mixed $actions): ?string
    {
        foreach ((array) $actions as $action) {
            if (is_array($action) && ($action['name'] ?? null) === 'generate-qr-code') {
                return (string) ($action['url'] ?? null);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('payment_gateway.providers.midtrans', []);

        return $config;
    }
}
