<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu proses import berkas mutasi rekening beserta ringkasan hasilnya.
 */
class BankStatementImport extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_statement_profile_id',
        'bank_account_id',
        'account_id',
        'user_id',
        'file_name',
        'file_path',
        'file_hash',
        'total_rows',
        'matched_rows',
        'unmatched_rows',
        'total_credit',
        'total_debit',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'matched_rows' => 'integer',
            'unmatched_rows' => 'integer',
            'total_credit' => 'decimal:2',
            'total_debit' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<BankStatementProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(BankStatementProfile::class, 'bank_statement_profile_id');
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    /**
     * Perbarui ringkasan dari baris yang tersimpan.
     */
    public function refreshSummary(): self
    {
        $this->forceFill([
            'total_rows' => $this->lines()->count(),
            'matched_rows' => $this->lines()->where('match_status', 'matched')->count(),
            'unmatched_rows' => $this->lines()->where('match_status', 'unmatched')->count(),
            'total_credit' => (float) $this->lines()->sum('credit'),
            'total_debit' => (float) $this->lines()->sum('debit'),
        ])->save();

        return $this;
    }
}
