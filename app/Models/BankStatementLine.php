<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReconciliationMatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris mutasi rekening koran hasil import.
 *
 * `credit` adalah uang masuk (penerimaan), `debit` uang keluar. Pencocokan
 * hanya dilakukan terhadap baris `credit` karena hanya itu yang dapat menjadi
 * pembayaran siswa.
 */
class BankStatementLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_statement_import_id',
        'transacted_at',
        'description',
        'reference',
        'debit',
        'credit',
        'balance',
        'row_number',
        'match_status',
        'match_rule',
        'match_confidence',
        'payment_id',
        'matched_by',
        'matched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transacted_at' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance' => 'decimal:2',
            'row_number' => 'integer',
            'match_status' => ReconciliationMatchStatus::class,
            'match_confidence' => 'decimal:2',
            'matched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BankStatementImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function isCredit(): bool
    {
        return (float) $this->credit > 0;
    }

    /**
     * @param  Builder<BankStatementLine>  $query
     */
    public function scopeCredits(Builder $query): void
    {
        $query->where('credit', '>', 0);
    }

    /**
     * @param  Builder<BankStatementLine>  $query
     */
    public function scopeUnmatched(Builder $query): void
    {
        $query->where('match_status', ReconciliationMatchStatus::Unmatched->value);
    }
}
