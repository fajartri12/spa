<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Program beasiswa / keringanan / subsidi silang.
 *
 * Satu program menghasilkan potongan dengan tipe dan nominal yang sama untuk
 * setiap siswa yang menerimanya. Penerimaan dicatat pada `scholarship_awards`
 * dan diturunkan menjadi `student_discounts` agar mesin tagihan yang sudah ada
 * (DiscountService) langsung memakainya tanpa perlakuan khusus.
 */
class ScholarshipProgram extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'sponsor',
        'funding_source',
        'discount_type',
        'discount_value',
        'payment_category_id',
        'academic_year_id',
        'quota',
        'valid_from',
        'valid_to',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'quota' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function paymentCategory(): BelongsTo
    {
        return $this->belongsTo(PaymentCategory::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(ScholarshipAward::class);
    }

    /**
     * @param  Builder<ScholarshipProgram>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<ScholarshipProgram>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }

    /**
     * @param  Builder<ScholarshipProgram>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('sponsor', 'like', "%{$term}%");
        });
    }

    /**
     * Program masih dalam masa berlaku dan dapat diberikan hari ini.
     */
    public function isAvailableOn(Carbon|string|null $date = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $date = $date === null ? Carbon::today() : Carbon::parse($date);

        if ($this->valid_from !== null && $date->lt($this->valid_from)) {
            return false;
        }

        return $this->valid_to === null || $date->lte($this->valid_to);
    }

    public function fundingSourceLabel(): string
    {
        return match ($this->funding_source) {
            'government' => 'Pemerintah',
            'private' => 'Swasta / Donatur',
            default => 'Internal Madrasah',
        };
    }
}
