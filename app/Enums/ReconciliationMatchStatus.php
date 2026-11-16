<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status pencocokan satu baris mutasi rekening koran.
 */
enum ReconciliationMatchStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Belum Cocok',
            self::Matched => 'Tercocokkan',
            self::Ignored => 'Diabaikan',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Unmatched => 'warning',
            self::Matched => 'success',
            self::Ignored => 'neutral',
        };
    }
}
