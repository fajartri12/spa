<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status keberadaan seorang alumni setelah lulus.
 *
 * Dipakai untuk memantau sebaran lulusan (studi lanjut, bekerja, wirausaha)
 * sehingga data alumni tidak berhenti pada nama dan tahun lulus saja.
 */
enum AlumniStatus: string
{
    case Unknown = 'unknown';
    case FurtherStudy = 'further-study';
    case Working = 'working';
    case Entrepreneur = 'entrepreneur';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Belum Diketahui',
            self::FurtherStudy => 'Melanjutkan Studi',
            self::Working => 'Bekerja',
            self::Entrepreneur => 'Wirausaha',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Unknown => 'neutral',
            self::FurtherStudy => 'info',
            self::Working => 'success',
            self::Entrepreneur => 'purple',
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
