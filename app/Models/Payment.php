<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = ['student_id', 'payment_category_id', 'academic_year_id', 'transaction_number', 'payment_intent_id', 'idempotency_key', 'paid_at', 'amount', 'status', 'notes'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'decimal:2', 'status' => PaymentStatus::class, 'idempotency_key' => 'string'];
    }

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function paymentCategory(): BelongsTo { return $this->belongsTo(PaymentCategory::class); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function details(): HasMany { return $this->hasMany(PaymentDetail::class); }
    public function allocations(): HasMany { return $this->hasMany(PaymentAllocation::class); }
    public function verifications(): HasMany { return $this->hasMany(PaymentVerification::class); }
    public function proofs(): HasMany { return $this->hasMany(PaymentProof::class); }
    public function cashTransactions(): HasMany { return $this->hasMany(CashTransaction::class); }

    /**
     * Tagihan digital (VA/QRIS) yang membentuk pembayaran ini, bila ada.
     *
     * @return BelongsTo<PaymentIntent, $this>
     */
    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    /**
     * Baris mutasi rekening yang dicocokkan dengan pembayaran ini.
     *
     * @return HasMany<BankStatementLine, $this>
     */
    public function reconciliationLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
