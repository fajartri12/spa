<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantDocument extends Model
{
    protected $fillable = ['applicant_id', 'document_type', 'original_name', 'storage_path', 'disk', 'mime_type', 'size', 'checksum', 'status'];

    protected function casts(): array { return ['size' => 'integer']; }

    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
}
