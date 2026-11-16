<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    public const DISK = 'local';

    public const DIRECTORY = 'backups';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'filename',
        'size',
        'created_by',
        'status',
        'notes',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function path(): string
    {
        return self::DIRECTORY.'/'.$this->filename;
    }

    public function humanSize(): string
    {
        $size = (int) $this->size;

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024) {
                return number_format($size, $unit === 'B' ? 0 : 1).' '.$unit;
            }

            $size /= 1024;
        }

        return number_format($size, 1).' TB';
    }
}
