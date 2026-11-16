<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pemetaan kolom berkas mutasi rekening untuk satu bank.
 *
 * Setiap bank memiliki tata letak ekspor berbeda. Profil menyimpan nama kolom
 * pada berkas sehingga format baru dapat ditambahkan lewat antarmuka tanpa
 * mengubah kode importer.
 */
class BankStatementProfile extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'bank_name',
        'header_row',
        'delimiter',
        'column_date',
        'column_description',
        'column_reference',
        'column_debit',
        'column_credit',
        'column_amount',
        'column_balance',
        'date_format',
        'decimal_separator',
        'thousands_separator',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'header_row' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<BankStatementImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(BankStatementImport::class);
    }

    /**
     * Peta nama kolom internal ke nama kolom pada berkas.
     *
     * @return array<string, string|null>
     */
    public function columnMap(): array
    {
        return [
            'transacted_at' => $this->column_date,
            'description' => $this->column_description,
            'reference' => $this->column_reference,
            'debit' => $this->column_debit,
            'credit' => $this->column_credit,
            'amount' => $this->column_amount,
            'balance' => $this->column_balance,
        ];
    }

    /**
     * Apakah berkas memakai kolom debit/kredit terpisah (bukan satu kolom
     * nominal bertanda).
     */
    public function usesDebitCreditColumns(): bool
    {
        return filled($this->column_debit) || filled($this->column_credit);
    }

    /**
     * @param  Builder<BankStatementProfile>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<BankStatementProfile>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }
}
