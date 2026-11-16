<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('bill_number', 60)->unique();
            $table->decimal('original_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('unpaid')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bill_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('student_bills'); }
};
