<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlumniStatus;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Data lulusan (alumni) per unit pendidikan.
 *
 * Satu alumni dapat tertaut ke satu siswa (`student_id`) agar penarikan data
 * lulusan dari daftar siswa tidak perlu mengetik ulang identitas. Namun
 * identitas utama disimpan sebagai salinan pada tabel ini supaya data alumni
 * angkatan lama tetap terbaca meskipun siswa sudah dihapus.
 */
class Alumni extends Model
{
    use SoftDeletes;

    /**
     * Nama tabel ditetapkan eksplisit karena bentuk jamak otomatis Laravel
     * ("alumnis") tidak sesuai kaidah kata "alumni" yang sudah jamak.
     *
     * @var string
     */
    protected $table = 'alumni';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'unit_id',
        'nis',
        'nisn',
        'name',
        'gender',
        'birth_place',
        'birth_date',
        'phone',
        'email',
        'address',
        'graduation_year',
        'class_name',
        'major',
        'status',
        'occupation',
        'workplace',
        'higher_education',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => AlumniStatus::class,
            'birth_date' => 'date',
            'graduation_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Alumni>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('graduation_year')->orderBy('name');
    }

    /**
     * @param  Builder<Alumni>  $query
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
                ->orWhere('class_name', 'like', "%{$term}%")
                ->orWhere('major', 'like', "%{$term}%")
                ->orWhere('occupation', 'like', "%{$term}%")
                ->orWhere('workplace', 'like', "%{$term}%")
                ->orWhere('higher_education', 'like', "%{$term}%");
        });
    }
}
