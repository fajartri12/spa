<?php

declare(strict_types=1);

namespace App\Enums;

enum BillStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Dibayar',
            self::Partial => 'Sebagian',
            self::Paid => 'Lunas',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
