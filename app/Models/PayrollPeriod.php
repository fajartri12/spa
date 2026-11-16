<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Periode penggajian / honorarium.
 *
 * Penomoran slip dan total dihitung dari `items`. Nilai `total_amount` dan
 * `employee_count` adalah salinan teragregasi agar daftar periode tidak perlu
 * menjumlahkan ribuan baris setiap kali dibuka.
 */
class PayrollPeriod extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'period',
        'title',
        'description',
        'unit_id',
        'academic_year_id',
        'cash_account_id',
        'expense_account_id',
        'status',
        'total_amount',
        'employee_count',
        'created_by',
        'approved_by',
        'approved_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'cash_transaction_id',
        'journal_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayrollStatus::class,
            'total_amount' => 'decimal:2',
            'employee_count' => 'integer',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->orderBy('sort_order')->orderBy('recipient_name');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<CashTransaction, $this>
     */
    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PayrollStatus::Draft;
    }

    /**
     * @param  Builder<PayrollPeriod>  $query
     */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', PayrollStatus::Draft->value);
    }

    /**
     * @param  Builder<PayrollPeriod>  $query
     */
    public function scopeForUnit(Builder $query, ?int $unitId): void
    {
        $query->when($unitId !== null, fn (Builder $inner) => $inner->where('unit_id', $unitId));
    }
}
