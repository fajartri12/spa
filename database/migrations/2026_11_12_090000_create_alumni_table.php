<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni', function (Blueprint $table): void {
            $table->id();

            // Tautan opsional ke data siswa. Alumni lama yang sudah tidak ada
            // di tabel siswa tetap dapat dicatat secara manual.
            $table->foreignId('student_id')
                ->nullable()
                ->unique()
                ->constrained('students')
                ->nullOnDelete();

            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();

            // Salinan identitas saat lulus agar jejak tetap utuh bila data
            // siswa berubah atau dihapus.
            $table->string('nis', 30)->nullable();
            $table->string('nisn', 30)->nullable();
            $table->string('name', 120)->index();
            $table->string('gender', 20)->default('male');
            $table->string('birth_place', 120)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();

            $table->unsignedSmallInteger('graduation_year')->index();
            $table->string('class_name', 60)->nullable();
            $table->string('major', 60)->nullable();

            $table->string('status', 30)->default('unknown')->index();
            $table->string('occupation', 120)->nullable();
            $table->string('workplace', 150)->nullable();
            $table->string('higher_education', 150)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'graduation_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni');
    }
};
