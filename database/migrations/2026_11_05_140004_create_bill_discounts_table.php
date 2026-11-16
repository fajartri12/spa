<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_discount_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('value', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index('student_bill_id');
        });
    }

    public function down(): void { Schema::dropIfExists('bill_discounts'); }
};
