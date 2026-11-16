<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('name', 60);
            $table->string('level', 40)->nullable();
            $table->string('homeroom_teacher', 120)->nullable();
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['unit_id', 'academic_year_id', 'name'], 'school_classes_unique_name');
            $table->index(['academic_year_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
