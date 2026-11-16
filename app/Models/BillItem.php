<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    protected $fillable = ['bill_id', 'description', 'amount', 'sort_order'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'sort_order' => 'integer']; }
    public function bill(): BelongsTo { return $this->belongsTo(Bill::class); }
}
