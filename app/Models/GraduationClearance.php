<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClearanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Status bebas tanggungan siswa sebagai prasyarat SKL.
 *
 * `remaining_amount` dan `outstanding_bills` adalah snapshot saat pemeriksaan
 * terakhir agar alasan penerbitan/penolakan SKL dapat diaudit.
 */
class GraduationClearance extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'academic_year_id',
        'status',
        'remaining_amount',
        'outstanding_bill_count',
        'outstanding_bills',
        'checked_by',
        'checked_at',
        'overridden_by',
        'overridden_at',
        'override_reason',
        'skl_number',
        'skl_issued_at',
        'skl_issued_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClearanceStatus::class,
            'remaining_amount' => 'decimal:2',
            'outstanding_bill_count' => 'integer',
            'outstanding_bills' => 'array',
            'checked_at' => 'datetime',
            'overridden_at' => 'datetime',
            'skl_issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    public function hasOutstanding(): bool
    {
        return (float) $this->remaining_amount > 0;
    }

    public function canIssueSkl(): bool
    {
        return $this->status->allowsIssuance();
    }

    /**
     * @param  Builder<GraduationClearance>  $query
     */
    public function scopeCleared(Builder $query): void
    {
        $query->whereIn('status', [
            ClearanceStatus::Cleared->value,
            ClearanceStatus::Overridden->value,
        ]);
    }

    /**
     * @param  Builder<GraduationClearance>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ClearanceStatus::Pending->value);
    }
}
