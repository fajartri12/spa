<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDetail extends Model
{
    protected $fillable = ['payment_id', 'payment_method_id', 'account_id', 'amount', 'reference_number', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
}
