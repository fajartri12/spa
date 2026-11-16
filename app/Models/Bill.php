<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'bill_number',
        'payment_category_id',
        'academic_year_id',
        'title',
        'description',
        'period',
        'due_date',
        'amount',
        'original_amount',
        'discount_amount',
        'total_amount',
        'remaining_amount',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'due_date' => 'date',
            'status' => BillStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<PaymentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PaymentCategory::class, 'payment_category_id');
    }

    /**
     * @return BelongsTo<PaymentCategory, $this>
     */
    public function paymentCategory(): BelongsTo
    {
        return $this->belongsTo(PaymentCategory::class, 'payment_category_id');
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return HasMany<StudentBill, $this>
     */
    public function studentBills(): HasMany
    {
        return $this->hasMany(StudentBill::class);
    }

    /**
     * @return HasMany<BillItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }
}
