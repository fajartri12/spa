<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Student extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'unit_id',
        'school_class_id',
        'nis',
        'nisn',
        'name',
        'gender',
        'birth_place',
        'birth_date',
        'religion',
        'address',
        'phone',
        'email',
        'parent_name',
        'parent_phone',
        'parent_relation',
        'entry_year',
        'status',
        'notes',
        'major',
        'portal_password',
        'portal_password_set_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'portal_password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'birth_date' => 'date',
            'entry_year' => 'integer',
            'major' => 'string',
            'portal_password' => 'hashed',
            'portal_password_set_at' => 'datetime',
        ];
    }

    /**
     * Generate password portal untuk siswa baru.
     */
    public static function generatePortalPassword(): string
    {
        return Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }

    /**
     * Apakah siswa sudah memiliki password portal sendiri.
     */
    public function hasPortalPassword(): bool
    {
        return is_string($this->portal_password) && $this->portal_password !== '';
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function savingsAccount(): HasOne
    {
        return $this->hasOne(StudentSavingsAccount::class);
    }

    /**
     * @return HasMany<StudentClassHistory, $this>
     */
    public function classHistories(): HasMany
    {
        return $this->hasMany(StudentClassHistory::class)->latest('id');
    }

    /**
     * Riwayat kelas pada satu tahun ajaran.
     */
    public function classHistoryFor(int $academicYearId): ?StudentClassHistory
    {
        return $this->classHistories()
            ->where('academic_year_id', $academicYearId)
            ->first();
    }

    /**
     * Data alumni bila siswa sudah lulus dan ditarik ke daftar alumni.
     *
     * @return HasOne<Alumni, $this>
     */
    public function alumni(): HasOne
    {
        return $this->hasOne(Alumni::class);
    }

    /**
     * Akun orang tua/wali yang dapat memantau siswa ini.
     *
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->using(GuardianStudent::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Tagihan digital (VA/QRIS) milik siswa.
     *
     * @return HasMany<PaymentIntent, $this>
     */
    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }

    /**
     * Status bebas tanggungan siswa.
     *
     * @return HasOne<GraduationClearance, $this>
     */
    public function clearance(): HasOne
    {
        return $this->hasOne(GraduationClearance::class);
    }

    /**
     * Tagihan yang dibebankan ke siswa ini.
     *
     * @return HasMany<StudentBill, $this>
     */
    public function studentBills(): HasMany
    {
        return $this->hasMany(StudentBill::class);
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', StudentStatus::Active->value);
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', "%{$term}%")
                ->orWhere('nis', 'like', "%{$term}%")
                ->orWhere('nisn', 'like', "%{$term}%")
                ->orWhere('major', 'like', "%{$term}%")
                ->orWhere('parent_name', 'like', "%{$term}%");
        });
    }
}
