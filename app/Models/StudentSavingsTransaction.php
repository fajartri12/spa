<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SavingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSavingsTransaction extends Model
{
    protected $fillable = [
        'student_savings_account_id', 'user_id', 'verified_by', 'transaction_number', 'type', 'status',
        'amount', 'balance_before', 'balance_after', 'transacted_at', 'description',
        'rejection_reason', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transacted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(StudentSavingsAccount::class, 'student_savings_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class);
    }

    public function isPending(): bool
    {
        return $this->status === SavingsService::STATUS_PENDING;
    }
}
