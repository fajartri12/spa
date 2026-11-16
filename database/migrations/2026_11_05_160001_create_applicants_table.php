<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_period_id')->constrained()->restrictOnDelete();
            $table->string('registration_number', 40)->unique();
            $table->string('name', 120);
            $table->string('email', 150)->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('birth_place', 120)->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('parent_name', 120)->nullable();
            $table->string('parent_phone', 30)->nullable();
            $table->string('status', 30)->default('registered')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['admission_period_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('applicants'); }
};
