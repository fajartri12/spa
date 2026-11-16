<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Siklus penagihan sebuah kategori pembayaran.
 */
enum PaymentCategoryType: string
{
    case Once = 'once';
    case Monthly = 'monthly';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Sekali Bayar',
            self::Monthly => 'Bulanan',
            self::Annual => 'Tahunan',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Once => 'neutral',
            self::Monthly => 'primary',
            self::Annual => 'purple',
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
