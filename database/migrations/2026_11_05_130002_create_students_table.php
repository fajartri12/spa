<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('nis', 30)->unique();
            $table->string('nisn', 30)->nullable()->unique();
            $table->string('name', 120)->index();
            $table->string('gender', 20)->default('male');
            $table->string('birth_place', 120)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('parent_name', 120)->nullable();
            $table->string('parent_phone', 30)->nullable();
            $table->string('parent_relation', 40)->nullable();
            $table->unsignedSmallInteger('entry_year')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'status']);
            $table->index(['school_class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
