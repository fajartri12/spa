<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionContent extends Model
{
    protected $fillable = ['admission_period_id', 'slug', 'title', 'body', 'is_published'];

    protected function casts(): array { return ['is_published' => 'boolean']; }

    public function admissionPeriod(): BelongsTo { return $this->belongsTo(AdmissionPeriod::class); }
}
