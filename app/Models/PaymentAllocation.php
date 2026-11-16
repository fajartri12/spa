<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'student_bill_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function studentBill(): BelongsTo { return $this->belongsTo(StudentBill::class); }
}
