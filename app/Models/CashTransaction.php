<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'payment_id', 'student_savings_transaction_id', 'admission_bill_id', 'user_id', 'transaction_number', 'transacted_at', 'type', 'amount', 'description', 'reference_number'];

    protected function casts(): array
    {
        return ['transacted_at' => 'datetime', 'type' => CashTransactionType::class, 'amount' => 'decimal:2'];
    }

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function savingsTransaction(): BelongsTo { return $this->belongsTo(StudentSavingsTransaction::class, 'student_savings_transaction_id'); }
    public function admissionBill(): BelongsTo { return $this->belongsTo(AdmissionBill::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
