<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Tabel penghubung wali dengan siswa.
 *
 * Dibuat sebagai model pivot agar `is_primary` selalu bertipe boolean, bukan
 * angka `0/1` mentah dari database.
 */
class GuardianStudent extends Pivot
{
    protected $table = 'guardian_student';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'guardian_id',
        'student_id',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
