<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\GatewayProvider;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\Gateway\GatewayCharge;
use App\Services\Gateway\GatewayException;
use App\Services\Gateway\GatewayManager;
use App\Services\Gateway\GatewayWebhookResult;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Penerbitan Virtual Account / QRIS dan penanganan webhook gateway.
 *
 * Aturan keuangan (AGENTS.MD bagian 6):
 * - Intent hanya diterbitkan untuk tagihan yang masih memiliki sisa.
 * - Satu tagihan hanya boleh memiliki satu intent terbuka; permintaan berulang
 *   mengembalikan intent yang sama agar tidak menumpuk nomor VA.
 * - Webhook yang sudah pernah diproses tidak membuat pembayaran kedua
 *   (idempoten lewat `payments.idempotency_key` berisi referensi intent).
 * - Nominal yang diterima harus sama dengan nominal intent. Bila berbeda,
 *   pembayaran TIDAK dibuat otomatis dan ditandai untuk verifikasi manual.
 */
final class PaymentGatewayService
{
    private const REFERENCE_ATTEMPTS = 3;

    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly PaymentService $payments,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Terbitkan tagihan digital untuk satu tagihan siswa.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws GatewayException
     * @throws InvalidArgumentException
     */
    public function createIntent(StudentBill $bill, GatewayProvider $provider, ?string $channel = null, array $options = []): PaymentIntent
    {
        if ((float) $bill->remaining_amount <= 0 || $bill->status === BillStatus::Paid) {
            throw new InvalidArgumentException('Tagihan ini sudah lunas, tidak dapat diterbitkan VA/QRIS.');
        }

        if ($bill->status === BillStatus::Cancelled) {
            throw new InvalidArgumentException('Tagihan yang dibatalkan tidak dapat diterbitkan VA/QRIS.');
        }

        $existing = PaymentIntent::query()
            ->where('student_bill_id', $bill->id)
            ->where('provider', $provider->value)
            ->open()
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $amount = round((float) $bill->remaining_amount, 2);

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->createIntentOnce($bill, $provider, $channel, $amount, $options);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::REFERENCE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function createIntentOnce(StudentBill $bill, GatewayProvider $provider, ?string $channel, float $amount, array $options): PaymentIntent
    {
        $intent = PaymentIntent::query()->create([
            'student_bill_id' => $bill->id,
            'student_id' => $bill->student_id,
            'provider' => $provider->value,
            'channel' => $channel,
            'reference' => $this->reference(),
            'amount' => $amount,
            'status' => PaymentIntentStatus::Pending->value,
            'expires_at' => now()->addHours((int) config('payment_gateway.expiry_hours', 24)),
        ]);

        try {
            $charge = $this->gateways->driver($provider)->createCharge($intent, ['channel' => $channel] + $options);
        } catch (GatewayException $exception) {
            // Jangan tinggalkan intent yatim tanpa nomor bayar.
            $intent->forceFill([
                'status' => PaymentIntentStatus::Failed->value,
                'provider_payload' => ['error' => $exception->getMessage()],
            ])->save();

            throw $exception;
        }

        $this->applyCharge($intent, $charge);
        $this->logger->log('payment_intent.created', "Menerbitkan {$provider->label()} untuk tagihan {$bill->bill_number}", $intent, [
            'reference' => $intent->reference,
            'amount' => $amount,
        ]);

        return $intent->refresh();
    }

    private function applyCharge(PaymentIntent $intent, GatewayCharge $charge): void
    {
        $intent->forceFill([
            'pay_code' => $charge->payCode,
            'qr_url' => $charge->qrUrl,
            'qr_string' => $charge->qrString,
            'provider_reference' => $charge->providerReference,
            'expires_at' => $charge->expiresAt !== null ? Carbon::parse($charge->expiresAt) : $intent->expires_at,
            'fee' => $charge->fee,
            'provider_payload' => $charge->raw === [] ? $intent->provider_payload : $charge->raw,
        ])->save();
    }

    /**
     * Proses notifikasi gateway. Idempoten: pemanggilan ulang tidak menambah
     * pembayaran baru.
     *
     * @throws GatewayException
     */
    public function handleWebhook(GatewayProvider $provider, GatewayWebhookResult $result): PaymentIntent
    {
        return DB::transaction(function () use ($provider, $result): PaymentIntent {
            $intent = PaymentIntent::query()
                ->where('reference', $result->reference)
                ->where('provider', $provider->value)
                ->lockForUpdate()
                ->first();

            if ($intent === null) {
                throw new GatewayException("Referensi {$result->reference} tidak dikenali.");
            }

            // Notifikasi berulang untuk intent yang sudah selesai diabaikan
            // tanpa mengubah apa pun.
            if (! $intent->status->isOpen()) {
                return $intent;
            }

            $intent->forceFill([
                'provider_payload' => $result->raw === [] ? $intent->provider_payload : $result->raw,
                'fee' => $result->fee > 0 ? $result->fee : $intent->fee,
                'provider_reference' => $result->providerReference ?? $intent->provider_reference,
            ])->save();

            if ($result->status !== PaymentIntentStatus::Paid) {
                $intent->forceFill(['status' => $result->status->value])->save();

                return $intent;
            }

            if (! $this->amountMatches($intent, $result->amount)) {
                // Nominal tidak sesuai: jangan buat pembayaran otomatis.
                Log::warning('Nominal webhook tidak sesuai dengan tagihan digital.', [
                    'reference' => $intent->reference,
                    'expected' => (float) $intent->amount,
                    'received' => $result->amount,
                ]);

                $intent->forceFill([
                    'status' => PaymentIntentStatus::Paid->value,
                    'paid_at' => $result->paidAt !== null ? Carbon::parse($result->paidAt) : now(),
                ])->save();

                $this->logger->log(
                    'payment_intent.amount_mismatch',
                    "Nominal {$provider->label()} tidak sesuai untuk {$intent->reference}; perlu verifikasi manual",
                    $intent,
                    ['expected' => (float) $intent->amount, 'received' => $result->amount],
                );

                return $intent;
            }

            $payment = $this->settle($intent, $result);

            $intent->forceFill([
                'status' => PaymentIntentStatus::Paid->value,
                'paid_at' => $result->paidAt !== null ? Carbon::parse($result->paidAt) : now(),
                'payment_id' => $payment->id,
            ])->save();

            return $intent;
        });
    }

    /**
     * Bentuk pembayaran terverifikasi dari tagihan digital yang dibayar.
     */
    private function settle(PaymentIntent $intent, GatewayWebhookResult $result): \App\Models\Payment
    {
        $bill = StudentBill::query()->with('bill')->lockForUpdate()->findOrFail($intent->student_bill_id);

        $payment = $this->payments->create(
            [
                'student_id' => $intent->student_id,
                'payment_category_id' => $bill->bill?->payment_category_id,
                'academic_year_id' => $bill->bill?->academic_year_id,
                'paid_at' => $result->paidAt !== null ? Carbon::parse($result->paidAt) : now(),
                'amount' => (float) $intent->amount,
                'notes' => "Pembayaran otomatis via {$intent->provider->label()} ({$intent->channel})",
                'idempotency_key' => 'intent:'.$intent->reference,
            ],
            [
                ['student_bill_id' => $bill->id, 'amount' => (float) $intent->amount],
            ],
        );

        if ($payment->payment_intent_id === null) {
            $payment->forceFill(['payment_intent_id' => $intent->id])->save();
        }

        // `PaymentService::create()` bersifat idempoten; bila sudah ada
        // pembayaran dengan kunci yang sama, status perlu dipastikan disetujui.
        if ($payment->status === \App\Enums\PaymentStatus::Pending) {
            $payment = $this->payments->approve($payment);
        }

        $intent->forceFill(['payment_id' => $payment->id])->save();

        return $payment;
    }

    private function amountMatches(PaymentIntent $intent, float $received): bool
    {
        if ($received <= 0) {
            return false;
        }

        return abs(round($received, 2) - round((float) $intent->amount, 2)) < 0.01;
    }

    /**
     * Tandai intent kedaluwarsa yang sudah lewat jatuh tempo.
     */
    public function expireOverdue(): int
    {
        return PaymentIntent::query()
            ->pending()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => PaymentIntentStatus::Expired->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * Batalkan tagihan digital yang belum dibayar (mis. tagihan dibatalkan).
     */
    public function cancel(PaymentIntent $intent, string $reason, User $actor): PaymentIntent
    {
        if (! $intent->status->isOpen()) {
            throw new InvalidArgumentException('Hanya tagihan digital yang masih menunggu pembayaran yang dapat dibatalkan.');
        }

        $intent->forceFill(['status' => PaymentIntentStatus::Cancelled->value])->save();

        $this->logger->log(
            'payment_intent.cancelled',
            "Membatalkan tagihan digital {$intent->reference}: {$reason}",
            $intent,
            ['reason' => $reason],
            $actor,
        );

        return $intent;
    }

    /**
     * Referensi unik yang aman dicetak pada berita transfer.
     */
    private function reference(): string
    {
        return 'PG'.$this->referenceDate().'-'.Str::upper(Str::random(10));
    }

    private function referenceDate(): string
    {
        return now()->format('ymd');
    }
}
