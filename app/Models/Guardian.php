<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Akun orang tua/wali yang dapat masuk ke portal orang tua.
 *
 * Login memakai nomor HP karena itu identitas yang paling mudah diingat dan
 * dimiliki seluruh wali. Satu akun boleh terhubung ke beberapa siswa agar
 * orang tua dengan anak lebih dari satu cukup memakai satu password.
 */
class Guardian extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'relation',
        'phone',
        'email',
        'occupation',
        'address',
        'is_active',
        'password',
        'password_set_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation' => GuardianRelation::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
        ];
    }

    public static function generatePassword(): string
    {
        return Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }

    public function hasPassword(): bool
    {
        return is_string($this->password) && $this->password !== '';
    }

    /**
     * Nomor HP dinormalisasi agar "+62 812-3456" dan "08123456" dianggap sama.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    public function setPhoneAttribute(string $value): void
    {
        $this->attributes['phone'] = self::normalizePhone($value);
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->using(GuardianStudent::class)
            ->withPivot('is_primary')
            ->withTimestamps()
            ->orderBy('name');
    }

    /**
     * Siswa aktif yang boleh ditampilkan pada portal.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function activeStudents(): BelongsToMany
    {
        return $this->students()->where('students.status', StudentStatus::Active->value);
    }

    /**
     * @param  Builder<Guardian>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Guardian>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhereHas('students', function (Builder $student) use ($term): void {
                    $student->where('name', 'like', "%{$term}%")
                        ->orWhere('nis', 'like', "%{$term}%");
                });
        });
    }
}
