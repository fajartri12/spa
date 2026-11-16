<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    public const CACHE_KEY = 'app_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'description',
        'is_public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(static fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * @param  Builder<AppSetting>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::rememberForever(self::CACHE_KEY, static fn (): array => static::query()
            ->pluck('value', 'key')
            ->all());

        return $settings[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value],
        );

        Cache::forget(self::CACHE_KEY);
    }
}
