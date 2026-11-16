<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status bebas tanggungan seorang siswa.
 *
 * Bebas tanggungan menjadi prasyarat penerbitan Surat Keterangan Lulus (SKL).
 * Status `overridden` berarti masih ada sisa tagihan tetapi bendahara
 * mengizinkan penerbitan dengan alasan tertulis.
 */
enum ClearanceStatus: string
{
    case Pending = 'pending';
    case Cleared = 'cleared';
    case Overridden = 'overridden';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum Bebas Tanggungan',
            self::Cleared => 'Bebas Tanggungan',
            self::Overridden => 'Dikecualikan',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Cleared => 'success',
            self::Overridden => 'info',
        };
    }

    /**
     * Boleh menerbitkan SKL.
     */
    public function allowsIssuance(): bool
    {
        return $this !== self::Pending;
    }
}
