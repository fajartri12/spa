<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nama role bawaan aplikasi.
 *
 * Nama internal menggunakan format slug bahasa Inggris (untuk kode),
 * sedangkan label() menyediakan label Bahasa Indonesia untuk antarmuka.
 */
enum RoleName: string
{
    case SuperAdministrator = 'super-administrator';
    case Administrator = 'administrator';
    case Treasurer = 'bendahara';
    case PaymentOfficer = 'petugas-pembayaran';
    case Verifier = 'verifikator';
    case Headmaster = 'kepala-sekolah';
    case AdmissionOperator = 'operator-ppdb';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Super Administrator',
            self::Administrator => 'Administrator',
            self::Treasurer => 'Bendahara',
            self::PaymentOfficer => 'Petugas Pembayaran',
            self::Verifier => 'Verifikator',
            self::Headmaster => 'Kepala Sekolah',
            self::AdmissionOperator => 'Operator PPDB',
        };
    }

    /**
     * Role yang memiliki akses penuh ke seluruh modul.
     *
     * @return array<int, self>
     */
    public static function privileged(): array
    {
        return [self::SuperAdministrator, self::Administrator];
    }
}
