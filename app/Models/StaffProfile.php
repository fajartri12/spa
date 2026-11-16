<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StaffPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'unit_id',
        'nip',
        'name',
        'gender',
        'position',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'position' => StaffPosition::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @param  Builder<StaffProfile>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<StaffProfile>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }

    /**
     * @param  Builder<StaffProfile>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', "%{$term}%")
                ->orWhere('nip', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }

    /**
     * Slip honorarium milik pegawai ini.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<PayrollItem, $this>
     */
    public function payrollItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
