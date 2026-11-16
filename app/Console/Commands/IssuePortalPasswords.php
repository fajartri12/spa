<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Student;
use App\Support\Csv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Penerbitan password portal sekali jalan untuk siswa yang belum memilikinya.
 *
 * Menggantikan password bersama (fallback) yang sudah dihapus.
 */
class IssuePortalPasswords extends Command
{
    protected $signature = 'students:issue-portal-passwords
                            {--force : Terbitkan ulang untuk siswa yang sudah memiliki password}';

    protected $description = 'Terbitkan password portal per siswa dan simpan daftarnya ke berkas privat';

    public function handle(): int
    {
        $query = Student::query();

        if (! $this->option('force')) {
            $query->whereNull('portal_password');
        }

        $students = $query->orderBy('name')->get();

        if ($students->isEmpty()) {
            $this->info('Semua siswa sudah memiliki password portal.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($students as $student) {
            $plain = Student::generatePortalPassword();

            $student->forceFill([
                'portal_password' => $plain,
                'portal_password_set_at' => now(),
            ])->save();

            $rows[] = [$student->nis, $student->nisn, $student->name, $plain];
        }

        $relativePath = 'portal-passwords-' . now()->format('YmdHis') . '.csv';
        $absolutePath = Storage::disk('local')->path($relativePath);

        $handle = fopen($absolutePath, 'w');

        if ($handle === false) {
            $this->error('Tidak dapat menulis berkas password.');

            return self::FAILURE;
        }

        fputcsv($handle, ['nis', 'nisn', 'name', 'password'], escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, Csv::safeAll($row), escape: '');
        }

        fclose($handle);

        $this->info(sprintf('%d password portal diterbitkan.', count($rows)));
        $this->line('Berkas: ' . $absolutePath);
        $this->warn('Bagikan password secara aman, lalu hapus berkas tersebut.');

        return self::SUCCESS;
    }
}
