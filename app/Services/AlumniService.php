<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\Alumni;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Penarikan data siswa lulus menjadi data alumni.
 *
 * Aturan:
 * - Hanya siswa berstatus `graduated` yang boleh ditarik.
 * - Satu siswa hanya boleh memiliki satu baris alumni.
 * - Identitas disalin dari data siswa agar alumni tetap terbaca bila siswa
 *   dihapus kemudian.
 */
final class AlumniService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Daftar siswa lulus yang belum memiliki data alumni.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Student>
     */
    public function candidates(?int $unitId = null, ?int $year = null): \Illuminate\Database\Eloquent\Collection
    {
        $linked = Alumni::query()->withTrashed()->whereNotNull('student_id')->select('student_id');

        return Student::query()
            ->with(['unit:id,name', 'schoolClass:id,name,academic_year_id', 'schoolClass.academicYear:id,name'])
            ->where('status', StudentStatus::Graduated->value)
            ->whereNotIn('id', $linked)
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->when($year !== null, fn ($query) => $query->where('entry_year', $year))
            ->ordered()
            ->get();
    }

    /**
     * Tarik satu siswa lulus menjadi alumni.
     */
    public function pullFromStudent(Student $student, User $actor, ?int $graduationYear = null): Alumni
    {
        if ($student->status !== StudentStatus::Graduated) {
            throw new InvalidArgumentException("Siswa {$student->name} belum berstatus lulus.");
        }

        $exists = Alumni::query()->withTrashed()->where('student_id', $student->id)->exists();

        if ($exists) {
            throw new InvalidArgumentException("Siswa {$student->name} sudah tercatat sebagai alumni.");
        }

        return DB::transaction(function () use ($student, $actor, $graduationYear): Alumni {
            $class = $student->schoolClass;

            $alumni = Alumni::query()->create([
                'student_id' => $student->id,
                'unit_id' => $student->unit_id,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'name' => $student->name,
                'gender' => $student->gender?->value ?? 'male',
                'birth_place' => $student->birth_place,
                'birth_date' => $student->birth_date,
                'phone' => $student->phone,
                'email' => $student->email,
                'address' => $student->address,
                'graduation_year' => $graduationYear ?? $this->resolveGraduationYear($student),
                'class_name' => $class?->name,
                'major' => $student->major,
                'created_by' => $actor->id,
            ]);

            $this->logger->log(
                'alumni.created',
                "Menarik data alumni {$alumni->name}",
                $alumni,
                ['student_id' => $student->id, 'graduation_year' => $alumni->graduation_year],
                $actor,
            );

            return $alumni;
        });
    }

    /**
     * Tarik banyak siswa lulus sekaligus.
     *
     * @param  array<int, int>  $studentIds
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    public function pullMany(array $studentIds, User $actor, ?int $graduationYear = null): array
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        $created = 0;
        $skipped = 0;
        $errors = [];

        if ($studentIds === []) {
            return ['created' => 0, 'skipped' => 0, 'errors' => []];
        }

        $students = Student::query()->whereIn('id', $studentIds)->get()->keyBy('id');

        foreach ($studentIds as $id) {
            $student = $students->get($id);

            if ($student === null) {
                $errors[] = "Siswa #{$id} tidak ditemukan.";
                $skipped++;

                continue;
            }

            try {
                $this->pullFromStudent($student, $actor, $graduationYear);
                $created++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Tahun lulus diambil dari tahun ajaran kelas terakhir, lalu jatuh pada
     * tahun masuk + 3 sebagai perkiraan terakhir bila kelas tidak diketahui.
     */
    private function resolveGraduationYear(Student $student): int
    {
        $class = $student->schoolClass;

        if ($class?->academicYear !== null) {
            return (int) $class->academicYear->end_date?->year;
        }

        $entryYear = (int) ($student->entry_year ?? 0);

        return $entryYear > 0 ? $entryYear + 3 : (int) now()->year;
    }
}
