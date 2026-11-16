<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentBill extends Model
{
    use SoftDeletes;
    protected $fillable = ['bill_id', 'student_id', 'bill_number', 'original_amount', 'discount_amount', 'final_amount', 'paid_amount', 'remaining_amount', 'due_date', 'status'];
    protected function casts(): array { return ['original_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'final_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'due_date' => 'date', 'status' => BillStatus::class]; }
    public function bill(): BelongsTo { return $this->belongsTo(Bill::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function discounts(): HasMany { return $this->hasMany(BillDiscount::class); }

    /**
     * Tagihan digital (VA/QRIS) yang pernah diterbitkan untuk tagihan ini.
     *
     * @return HasMany<PaymentIntent, $this>
     */
    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }
}
