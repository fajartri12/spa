<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Applicant;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Konversi pendaftar PPDB menjadi siswa aktif.
 *
 * PPDB dan data induk siswa sebelumnya terpisah total, sehingga siswa yang
 * diterima harus diinput ulang secara manual. Service ini menyalin data
 * pendaftar ke `students`, membuat penempatan kelas awal, dan menautkan
 * kembali pendaftar ke siswa hasil konversi agar tidak terjadi duplikasi.
 */
final class ApplicantConversionService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array{unit_id:int, school_class_id:?int, nis:string, entry_year:?int}  $data
     */
    public function convert(Applicant $applicant, array $data, User $actor): Student
    {
        if ($applicant->isConverted()) {
            throw new InvalidArgumentException('Pendaftar ini sudah dikonversi menjadi siswa.');
        }

        $nis = trim((string) $data['nis']);

        if ($nis === '') {
            throw new InvalidArgumentException('NIS wajib diisi.');
        }

        if (Student::withTrashed()->where('nis', $nis)->exists()) {
            throw new InvalidArgumentException('NIS sudah digunakan oleh siswa lain.');
        }

        $class = null;

        if (! empty($data['school_class_id'])) {
            $class = SchoolClass::query()->find((int) $data['school_class_id']);

            if ($class === null) {
                throw new InvalidArgumentException('Kelas tujuan tidak ditemukan.');
            }

            if ((int) $class->unit_id !== (int) $data['unit_id']) {
                throw new InvalidArgumentException('Kelas tujuan berada pada unit yang berbeda.');
            }
        }

        return DB::transaction(function () use ($applicant, $data, $class, $nis, $actor): Student {
            $applicant = Applicant::query()->lockForUpdate()->findOrFail($applicant->getKey());

            if ($applicant->isConverted()) {
                throw new InvalidArgumentException('Pendaftar ini sudah dikonversi menjadi siswa.');
            }

            $student = Student::query()->create([
                'unit_id' => (int) $data['unit_id'],
                'school_class_id' => $class?->id,
                'nis' => $nis,
                'name' => $applicant->name,
                'gender' => $this->gender($applicant->gender),
                'birth_place' => $applicant->birth_place,
                'birth_date' => $applicant->birth_date,
                'address' => $applicant->address,
                'phone' => $applicant->phone,
                'email' => $applicant->email,
                'parent_name' => $applicant->parent_name,
                'parent_phone' => $applicant->parent_phone,
                'entry_year' => $data['entry_year'] ?? (int) now()->year,
                'status' => StudentStatus::Active,
                'notes' => $applicant->notes,
            ]);

            if ($class !== null) {
                StudentClassHistory::query()->create([
                    'student_id' => $student->id,
                    'school_class_id' => $class->id,
                    'academic_year_id' => $class->academic_year_id,
                    'unit_id' => $class->unit_id,
                    'status' => StudentStatus::Active->value,
                    'class_name' => $class->name,
                    'note' => 'Penempatan awal dari PPDB',
                    'created_by' => $actor->id,
                ]);
            }

            $applicant->forceFill([
                'student_id' => $student->id,
                'converted_at' => now(),
                'status' => 'passed',
            ])->save();

            $this->logger->log(
                'admissions.converted',
                "Pendaftar {$applicant->registration_number} dikonversi menjadi siswa {$student->nis}",
                $student,
                [
                    'applicant_id' => $applicant->id,
                    'unit_id' => $student->unit_id,
                    'school_class_id' => $student->school_class_id,
                ],
                $actor,
            );

            return $student;
        });
    }

    /**
     * Pendaftar menyimpan gender sebagai string bebas; siswa memakai enum.
     */
    private function gender(?string $gender): Gender
    {
        $normalised = strtolower(trim((string) $gender));

        return in_array($normalised, ['female', 'perempuan', 'p', 'f'], true)
            ? Gender::Female
            : Gender::Male;
    }
}
