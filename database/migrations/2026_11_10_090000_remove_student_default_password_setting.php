<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hapus pengaturan `student_default_password` yang menyimpan kata sandi
 * plaintext dan sudah tidak dipakai sejak portal siswa memakai password
 * per siswa (temuan audit H-01 / L-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->where('key', 'student_default_password')->delete();
    }

    public function down(): void
    {
        // Sengaja tidak dipulihkan: menyimpan kata sandi plaintext tidak aman.
    }
};
