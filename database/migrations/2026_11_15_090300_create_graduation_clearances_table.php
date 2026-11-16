<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bebas tanggungan sebagai prasyarat Surat Keterangan Lulus (SKL).
 *
 * Baris dibuat saat pemeriksaan dijalankan (atau otomatis saat SKL diminta).
 * `remaining_amount` adalah salinan sisa tagihan pada saat pemeriksaan agar
 * keputusan penerbitan SKL dapat diaudit tanpa menghitung ulang saldo yang
 * mungkin sudah berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_clearances', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 20)->default('pending')->index();
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->unsignedInteger('outstanding_bill_count')->default(0);

            // Ringkasan tagihan yang belum lunas saat pemeriksaan berjalan.
            $table->json('outstanding_bills')->nullable();

            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checked_at')->nullable();

            // Pengecualian (override) oleh bendahara beserta alasannya.
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('overridden_at')->nullable();
            $table->text('override_reason')->nullable();

            $table->string('skl_number', 60)->nullable()->unique();
            $table->dateTime('skl_issued_at')->nullable();
            $table->foreignId('skl_issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Satu siswa satu baris pemeriksaan; pemeriksaan ulang memperbarui
            // baris yang sama agar nomor SKL tidak hilang.
            $table->unique('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_clearances');
    }
};
