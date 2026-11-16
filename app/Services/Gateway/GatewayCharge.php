<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Enums\GatewayProvider;

/**
 * Hasil pembuatan tagihan digital pada penyedia.
 *
 * Semua penyedia dipetakan ke bentuk yang sama agar domain tidak bergantung
 * pada format respons masing-masing gateway.
 */
final readonly class GatewayCharge
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public GatewayProvider $provider,
        public string $reference,
        public ?string $payCode = null,
        public ?string $qrUrl = null,
        public ?string $qrString = null,
        public ?string $providerReference = null,
        public ?string $expiresAt = null,
        public float $fee = 0.0,
        public array $raw = [],
    ) {}
}
