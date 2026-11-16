<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Siklus hidup periode penggajian/honorarium.
 *
 * - Draft: slip masih dapat diubah, belum ada uang keluar.
 * - Approved: slip dikunci dan satu transaksi kas keluar beserta jurnal batch
 *   sudah dibentuk.
 * - Cancelled: dibatalkan dengan alasan; kas dan jurnal dibalik, bukan dihapus.
 */
enum PayrollStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Approved => 'Disetujui',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Approved => 'success',
            self::Cancelled => 'danger',
        };
    }
}
