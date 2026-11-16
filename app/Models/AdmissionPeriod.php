<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdmissionPeriod extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'starts_at', 'ends_at', 'status', 'description'];

    protected function casts(): array { return ['starts_at' => 'date', 'ends_at' => 'date']; }

    public function applicants(): HasMany { return $this->hasMany(Applicant::class); }
    public function contents(): HasMany { return $this->hasMany(AdmissionContent::class); }
}
