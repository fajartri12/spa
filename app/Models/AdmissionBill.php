<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionBill extends Model
{
    protected $fillable = ['applicant_id', 'bill_number', 'title', 'amount', 'due_date', 'paid_at', 'status'];

    protected function casts(): array { return ['amount' => 'decimal:2', 'due_date' => 'date', 'paid_at' => 'datetime']; }

    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
    public function cashTransactions(): HasMany { return $this->hasMany(CashTransaction::class); }
}
