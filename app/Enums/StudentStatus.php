<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status siswa pada sebuah unit pendidikan.
 */
enum StudentStatus: string
{
    case Active = 'active';
    case Graduated = 'graduated';
    case Transferred = 'transferred';
    case DroppedOut = 'dropped-out';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Graduated => 'Lulus',
            self::Transferred => 'Pindah',
            self::DroppedOut => 'Keluar',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Graduated => 'info',
            self::Transferred => 'warning',
            self::DroppedOut => 'danger',
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
