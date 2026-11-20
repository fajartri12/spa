<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penerimaan program beasiswa oleh seorang siswa.
 *
 * `student_discount_id` menautkan ke aturan potongan yang benar-benar dipakai
 * mesin tagihan, sehingga riwayat potongan tetap dapat diaudit walau program
 * dihentikan.
 */
class ScholarshipAward extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'scholarship_program_id',
        'student_id',
        'student_discount_id',
        'status',
        'starts_on',
        'ends_on',
        'notes',
        'awarded_by',
        'revoked_at',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class, 'scholarship_program_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(StudentDiscount::class, 'student_discount_id');
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    /**
     * @param  Builder<ScholarshipAward>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
