<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Enums\GatewayProvider;
use App\Enums\PaymentIntentStatus;

/**
 * Hasil normalisasi notifikasi (webhook) dari penyedia.
 *
 * `reference` wajib sama dengan `payment_intents.reference` agar pembayaran
 * dapat ditautkan. Nilai `amount` adalah nominal yang benar-benar diterima,
 * bukan nominal tagihan.
 */
final readonly class GatewayWebhookResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public GatewayProvider $provider,
        public string $reference,
        public PaymentIntentStatus $status,
        public float $amount = 0.0,
        public ?string $paidAt = null,
        public ?string $providerReference = null,
        public float $fee = 0.0,
        public array $raw = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->status === PaymentIntentStatus::Paid;
    }
}
