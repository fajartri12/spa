<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status tagihan digital (Virtual Account / QRIS) pada payment gateway.
 */
enum PaymentIntentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Dibayar',
            self::Expired => 'Kedaluwarsa',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Expired => 'neutral',
            self::Failed, self::Cancelled => 'danger',
        };
    }

    /**
     * Status yang masih dapat menerima pembayaran.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
