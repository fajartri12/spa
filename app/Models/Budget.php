<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rencana Anggaran Pendapatan dan Belanja Madrasah (RAPBM) per akun.
 *
 * Realisasi tidak disimpan di sini: nilainya dihitung dari jurnal yang sudah
 * posted sehingga angka anggaran tidak pernah menyimpang dari buku besar.
 */
class Budget extends Model
{
    protected $fillable = ['academic_year_id', 'account_id', 'planned_amount', 'notes'];

    protected function casts(): array
    {
        return ['planned_amount' => 'decimal:2'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
