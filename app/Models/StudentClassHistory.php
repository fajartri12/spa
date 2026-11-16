<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat penempatan kelas siswa per tahun ajaran.
 *
 * `students.school_class_id` hanya menyimpan kondisi terkini. Tabel ini
 * menyimpan jejak setiap perpindahan/kenaikan kelas agar laporan per tahun
 * ajaran tetap benar meskipun siswa sudah naik kelas.
 */
class StudentClassHistory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'school_class_id',
        'academic_year_id',
        'unit_id',
        'status',
        'class_name',
        'note',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
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
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
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
}
