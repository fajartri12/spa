<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillDiscount extends Model
{
    protected $fillable = ['student_bill_id', 'student_discount_id', 'type', 'value', 'amount', 'reason'];
    protected function casts(): array { return ['value' => 'decimal:2', 'amount' => 'decimal:2']; }
    public function studentBill(): BelongsTo { return $this->belongsTo(StudentBill::class); }
    public function studentDiscount(): BelongsTo { return $this->belongsTo(StudentDiscount::class); }
}
