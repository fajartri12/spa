<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

/**
 * Jumlah baris per halaman untuk daftar data.
 *
 * Nilai diambil dari pengaturan sistem (`rows_per_page`) agar admin dapat
 * menyesuaikan kepadatan tabel tanpa mengubah kode. Nilai dijepit ke rentang
 * aman supaya tidak ada halaman berisi ribuan baris karena salah input.
 */
final class Pagination
{
    public const DEFAULT = 15;

    public const MIN = 5;

    public const MAX = 100;

    public static function perPage(): int
    {
        $value = (int) AppSetting::get('rows_per_page', (string) self::DEFAULT);

        return max(self::MIN, min(self::MAX, $value));
    }
}
