<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->restrictOnDelete();
            $table->string('bill_number', 50)->unique();
            $table->string('title', 150);
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('unpaid')->index();
            $table->timestamps();
            $table->index(['applicant_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('admission_bills'); }
};
