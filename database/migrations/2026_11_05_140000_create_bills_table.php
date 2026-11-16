<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('bill_number', 50)->unique();
            $table->foreignId('payment_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('period', 20)->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('original_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['academic_year_id', 'payment_category_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('bills'); }
};
