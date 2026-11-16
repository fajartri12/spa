<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Aturan penautan wali dengan siswa.
 *
 * Satu siswa boleh punya beberapa wali, tetapi hanya satu yang ditandai
 * sebagai wali utama (kontak pertama saat sekolah menghubungi keluarga).
 */
class GuardianService
{
    /**
     * @param  array<int, int|string>  $studentIds
     */
    public function syncStudents(Guardian $guardian, array $studentIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $studentIds)));

        DB::transaction(function () use ($guardian, $ids): void {
            $guardian->students()->sync($this->pivotPayload($guardian, $ids));

            // Siswa tanpa wali utama otomatis memakai wali yang baru ditautkan.
            foreach ($ids as $studentId) {
                $hasPrimary = DB::table('guardian_student')
                    ->where('student_id', $studentId)
                    ->where('is_primary', true)
                    ->exists();

                if (! $hasPrimary) {
                    DB::table('guardian_student')
                        ->where('student_id', $studentId)
                        ->where('guardian_id', $guardian->getKey())
                        ->update(['is_primary' => true]);
                }
            }
        });
    }

    /**
     * Terbitkan password portal baru dan kembalikan bentuk plainnya.
     */
    public function issuePassword(Guardian $guardian): string
    {
        $plain = Guardian::generatePassword();

        $guardian->forceFill([
            'password' => $plain,
            'password_set_at' => now(),
        ])->save();

        return $plain;
    }

    /**
     * Buat akun wali dari kolom orang tua pada data siswa.
     *
     * Dipakai sebagai jembatan agar sekolah tidak perlu memasukkan ulang data
     * yang sudah ada di `students.parent_*`.
     */
    public function importFromStudent(Student $student): ?Guardian
    {
        $name = trim((string) $student->parent_name);
        $phone = Guardian::normalizePhone((string) $student->parent_phone);

        if ($name === '' || $phone === '') {
            return null;
        }

        $guardian = Guardian::withTrashed()->firstOrNew(['phone' => $phone]);
        $guardian->name = $guardian->exists ? $guardian->name : $name;
        $guardian->relation = \App\Enums\GuardianRelation::fromLabel($student->parent_relation) ?? \App\Enums\GuardianRelation::Guardian;
        $guardian->is_active = true;

        if ($guardian->trashed()) {
            $guardian->restore();
        }

        $guardian->save();

        $this->syncStudents($guardian, [$student->getKey()]);

        return $guardian;
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return array<int, array<string, mixed>>
     */
    private function pivotPayload(Guardian $guardian, array $studentIds): array
    {
        $existing = DB::table('guardian_student')
            ->where('guardian_id', $guardian->getKey())
            ->pluck('is_primary', 'student_id');

        $payload = [];

        foreach ($studentIds as $studentId) {
            // `sync` menghapus tautan yang tidak lagi dipilih, jadi flag wali
            // utama harus dipertahankan agar tidak hilang saat edit.
            $payload[$studentId] = [
                'is_primary' => (bool) ($existing[$studentId] ?? false),
            ];
        }

        return $payload;
    }
}
