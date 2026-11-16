<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    protected $fillable = ['payment_id', 'file_path', 'original_name', 'mime_type', 'file_size'];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
