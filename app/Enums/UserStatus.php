<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status akun pengguna aplikasi.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
            self::Suspended => 'Ditangguhkan',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'neutral',
            self::Suspended => 'danger',
        };
    }
}
