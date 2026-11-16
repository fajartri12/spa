<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('value', 15, 2)->default(0);
            $table->string('reason', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['student_id', 'payment_category_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('student_discounts'); }
};
