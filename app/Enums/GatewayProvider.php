<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Penyedia payment gateway yang didukung.
 *
 * Driver implementasi berada pada `App\Services\Gateway`. Menambah penyedia
 * baru cukup dengan menambah case di sini, driver, dan entri pada
 * `config/payment_gateway.php`.
 */
enum GatewayProvider: string
{
    case Tripay = 'tripay';
    case Midtrans = 'midtrans';
    case Fake = 'fake';

    public function label(): string
    {
        return match ($this) {
            self::Tripay => 'Tripay',
            self::Midtrans => 'Midtrans',
            self::Fake => 'Simulator (Pengujian)',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Tripay => 'primary',
            self::Midtrans => 'info',
            self::Fake => 'neutral',
        };
    }

    /**
     * Penyedia yang boleh dipilih unit pada pengaturan.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach ([self::Tripay, self::Midtrans] as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
