<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalDetail extends Model
{
    protected $fillable = ['journal_id', 'account_id', 'debit', 'credit', 'description'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
}
