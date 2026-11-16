<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GatewayProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'level',
        'payment_provider',
        'payment_channel',
        'payment_gateway_enabled',
        'description',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'payment_provider' => GatewayProvider::class,
            'payment_gateway_enabled' => 'boolean',
        ];
    }

    /**
     * Penyedia gateway yang dipakai unit ini, atau null bila belum diatur.
     */
    public function gatewayProvider(): ?GatewayProvider
    {
        return $this->payment_gateway_enabled ? $this->payment_provider : null;
    }

    /**
     * @param  Builder<Unit>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Unit>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @param  Builder<Unit>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('level', 'like', "%{$term}%");
        });
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * @return HasMany<Alumni, $this>
     */
    public function alumni(): HasMany
    {
        return $this->hasMany(Alumni::class);
    }
}
