<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Guardian;
use App\Models\Student;
use App\Services\GuardianService;
use App\Support\Csv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Menerbitkan akun portal orang tua dari kolom `students.parent_*`.
 *
 * Sekolah biasanya sudah mengisi nama dan nomor HP wali pada data siswa.
 * Perintah ini memanfaatkannya agar tidak perlu memasukkan data dua kali,
 * sekaligus menerbitkan password portal untuk wali yang belum memilikinya.
 */
class IssueGuardianAccounts extends Command
{
    protected $signature = 'guardians:issue-accounts
                            {--force : Terbitkan ulang password untuk wali yang sudah memilikinya}';

    protected $description = 'Buat akun portal orang tua dari data wali pada siswa dan simpan daftar password ke berkas privat';

    public function handle(GuardianService $guardians): int
    {
        $service = $guardians;
        $students = Student::query()
            ->whereNotNull('parent_name')
            ->whereNotNull('parent_phone')
            ->where('parent_phone', '!=', '')
            ->orderBy('name')
            ->get();

        $rows = [];
        $skipped = 0;

        foreach ($students as $student) {
            $guardian = $service->importFromStudent($student);

            if ($guardian === null) {
                $skipped++;

                continue;
            }

            $alreadyHad = $guardian->hasPassword();

            if ($alreadyHad && ! $this->option('force')) {
                continue;
            }

            $plain = $service->issuePassword($guardian);

            $rows[] = [
                $guardian->name,
                $guardian->phone,
                $student->name,
                $student->nis,
                $plain,
            ];
        }

        if ($rows === []) {
            $this->info('Tidak ada akun portal orang tua yang perlu diterbitkan.');

            return self::SUCCESS;
        }

        $relativePath = 'guardian-passwords-'.now()->format('YmdHis').'.csv';
        $absolutePath = Storage::disk('local')->path($relativePath);

        $handle = fopen($absolutePath, 'w');

        if ($handle === false) {
            $this->error('Tidak dapat menulis berkas password.');

            return self::FAILURE;
        }

        fputcsv($handle, ['nama_wali', 'no_hp', 'nama_siswa', 'nis', 'password'], escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, Csv::safeAll($row), escape: '');
        }

        fclose($handle);

        $this->info(sprintf('%d akun orang tua diterbitkan.', count($rows)));

        if ($skipped > 0) {
            $this->warn(sprintf('%d siswa dilewati karena data wali tidak lengkap.', $skipped));
        }

        $this->line('Berkas: '.$absolutePath);
        $this->warn('Bagikan password secara aman, lalu hapus berkas tersebut.');

        return self::SUCCESS;
    }
}
