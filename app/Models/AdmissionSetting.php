<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionSetting extends Model
{
    protected $fillable = ['key', 'value', 'is_encrypted'];

    protected function casts(): array { return ['is_encrypted' => 'boolean']; }
}
