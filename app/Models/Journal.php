<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'journal_number', 'journal_date', 'description', 'reference_number', 'status'];

    protected function casts(): array
    {
        return ['journal_date' => 'date', 'status' => JournalStatus::class];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function details(): HasMany { return $this->hasMany(JournalDetail::class); }
}
