<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kenaikan kelas / perpindahan kelas siswa.
 *
 * Aturan:
 * - Setiap perubahan menulis satu baris `student_class_histories` sebagai jejak.
 * - Kelas tujuan harus berada pada unit dan tahun ajaran yang sama dengan
 *   permintaan; siswa tidak boleh dipindah lintas unit dalam satu operasi.
 * - Status siswa (aktif/lulus/pindah/keluar) dapat diubah bersamaan agar
 *   kenaikan kelas dan kelulusan selesai dalam satu langkah.
 */
final class PromotionService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ClearanceService $clearances,
    ) {}

    /**
     * Daftar siswa yang akan terkena promosi beserta kelas asalnya.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Student>
     */
    public function candidates(array $studentIds): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return Student::query()
            ->with(['schoolClass.academicYear', 'unit'])
            ->whereIn('id', $studentIds)
            ->ordered()
            ->get();
    }

    /**
     * Jalankan promosi untuk sekumpulan siswa.
     *
     * Bila status tujuan adalah `graduated`, bebas tanggungan diperiksa lebih
     * dulu: siswa yang masih memiliki sisa tagihan menggagalkan seluruh operasi
     * agar kelulusan tidak tercatat sebelum administrasinya selesai. Pengecualian
     * (override) yang sudah dicatat bendahara tetap dihormati.
     *
     * @param  array<int, int>  $studentIds
     * @return array{promoted: int, skipped: int}
     */
    public function promote(
        array $studentIds,
        int $targetClassId,
        ?int $targetYearId,
        ?StudentStatus $status,
        ?string $note,
        User $actor,
        bool $enforceClearance = true,
    ): array {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        if ($studentIds === []) {
            throw new InvalidArgumentException('Pilih minimal satu siswa untuk diproses.');
        }

        $targetClass = SchoolClass::query()->find($targetClassId);

        if ($targetClass === null) {
            throw new InvalidArgumentException('Kelas tujuan tidak ditemukan.');
        }

        $targetYearId = $targetYearId ?? (int) $targetClass->academic_year_id;

        if ((int) $targetClass->academic_year_id !== $targetYearId) {
            throw new InvalidArgumentException('Kelas tujuan tidak berada pada tahun ajaran yang dipilih.');
        }

        $year = AcademicYear::query()->find($targetYearId);

        if ($year === null) {
            throw new InvalidArgumentException('Tahun ajaran tujuan tidak ditemukan.');
        }

        return DB::transaction(function () use ($studentIds, $targetClass, $year, $status, $note, $actor, $enforceClearance): array {
            $students = Student::query()->lockForUpdate()->whereIn('id', $studentIds)->get();

            if ($students->count() !== count($studentIds)) {
                throw new InvalidArgumentException('Sebagian siswa yang dipilih tidak ditemukan.');
            }

            $unitIds = $students->pluck('unit_id')->unique();

            if ($unitIds->count() > 1) {
                throw new InvalidArgumentException('Siswa dari beberapa unit tidak dapat diproses dalam satu operasi.');
            }

            if ((int) $unitIds->first() !== (int) $targetClass->unit_id) {
                throw new InvalidArgumentException('Kelas tujuan berada pada unit yang berbeda dengan siswa.');
            }

            if ($enforceClearance && $status === StudentStatus::Graduated) {
                $this->assertClearance($students, $actor);
            }

            $promoted = 0;
            $skipped = 0;

            foreach ($students as $student) {
                $unchanged = (int) $student->school_class_id === (int) $targetClass->id
                    && ($status === null || $student->status === $status);

                if ($unchanged) {
                    $skipped++;

                    continue;
                }

                $before = [
                    'school_class_id' => $student->school_class_id,
                    'status' => $student->status?->value,
                ];

                $student->forceFill(array_filter([
                    'school_class_id' => $targetClass->id,
                    'status' => $status?->value,
                ], static fn ($value) => $value !== null))->save();

                StudentClassHistory::query()->create([
                    'student_id' => $student->id,
                    'school_class_id' => $targetClass->id,
                    'academic_year_id' => $year->id,
                    'unit_id' => $targetClass->unit_id,
                    'status' => ($status ?? $student->status)?->value ?? StudentStatus::Active->value,
                    'class_name' => $targetClass->name,
                    'note' => $note,
                    'created_by' => $actor->id,
                ]);

                $this->logger->log(
                    'students.promoted',
                    "Kenaikan kelas {$student->name} ke {$targetClass->name}",
                    $student,
                    [
                        'before' => $before,
                        'after' => [
                            'school_class_id' => $targetClass->id,
                            'status' => ($status ?? $student->status)?->value,
                        ],
                        'academic_year' => $year->name,
                    ],
                    $actor,
                );

                $promoted++;
            }

            return ['promoted' => $promoted, 'skipped' => $skipped];
        });
    }

    /**
     * Pastikan seluruh siswa bebas tanggungan sebelum dinyatakan lulus.
     *
     * @param  Collection<int, Student>  $students
     *
     * @throws InvalidArgumentException
     */
    private function assertClearance(Collection $students, User $actor): void
    {
        $blocked = [];

        foreach ($students as $student) {
            $clearance = $this->clearances->check($student, $actor);

            if (! $clearance->status->allowsIssuance()) {
                $blocked[] = $student->name.' (Rp '.number_format((float) $clearance->remaining_amount, 0, ',', '.').')';
            }
        }

        if ($blocked !== []) {
            throw new InvalidArgumentException(
                'Kelulusan dibatalkan karena masih ada tanggungan: '.implode(', ', $blocked)
                .'. Selesaikan tagihan atau catat pengecualian bebas tanggungan terlebih dahulu.'
            );
        }
    }

    /**
     * Siswa terpilih yang belum bebas tanggungan, untuk peringatan di wizard.
     *
     * Siswa yang belum pernah diperiksa ikut ditampilkan karena statusnya belum
     * diketahui; pemeriksaan sebenarnya dijalankan saat promosi diproses.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, array{student: Student, remaining: float, status: ?\App\Enums\ClearanceStatus}>
     */
    public function clearanceBlockers(array $studentIds): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return Student::query()
            ->with('clearance')
            ->whereIn('id', array_map('intval', $studentIds))
            ->ordered()
            ->get()
            ->filter(static fn (Student $student): bool => $student->clearance === null
                || ! $student->clearance->status->allowsIssuance())
            ->map(static fn (Student $student): array => [
                'student' => $student,
                'remaining' => (float) ($student->clearance?->remaining_amount ?? 0),
                'status' => $student->clearance?->status,
            ])
            ->values();
    }

    /**
     * Catat penempatan kelas awal bila siswa belum memiliki riwayat pada
     * tahun ajaran kelasnya. Dipakai saat backfill data lama.
     */
    public function recordInitialPlacement(Student $student, ?User $actor = null): ?StudentClassHistory
    {
        $class = $student->schoolClass;

        if ($class === null || $class->academic_year_id === null) {
            return null;
        }

        $exists = StudentClassHistory::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $class->academic_year_id)
            ->exists();

        if ($exists) {
            return null;
        }

        return StudentClassHistory::query()->create([
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'unit_id' => $class->unit_id,
            'status' => ($student->status?->value) ?? StudentStatus::Active->value,
            'class_name' => $class->name,
            'note' => 'Penempatan awal',
            'created_by' => $actor?->id,
        ]);
    }
}
