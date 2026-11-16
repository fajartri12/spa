<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jabatan petugas pada struktur komite / madrasah.
 */
enum StaffPosition: string
{
    case Headmaster = 'kepala-madrasah';
    case Treasurer = 'bendahara';
    case PaymentOfficer = 'petugas-pembayaran';
    case Verifier = 'verifikator';
    case AdmissionOperator = 'operator-ppdb';
    case Teacher = 'guru';
    case AdministrativeStaff = 'tata-usaha';

    public function label(): string
    {
        return match ($this) {
            self::Headmaster => 'Kepala Madrasah',
            self::Treasurer => 'Bendahara',
            self::PaymentOfficer => 'Petugas Pembayaran',
            self::Verifier => 'Verifikator',
            self::AdmissionOperator => 'Operator PPDB',
            self::Teacher => 'Guru',
            self::AdministrativeStaff => 'Tata Usaha',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
