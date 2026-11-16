<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVerification extends Model
{
    protected $fillable = ['payment_id', 'verified_by', 'status', 'notes', 'verified_at'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'verified_at' => 'datetime'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
