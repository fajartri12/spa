<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_savings_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('student_savings_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_savings_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_number', 40)->unique();
            $table->enum('type', ['deposit', 'withdrawal']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->dateTime('transacted_at');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['type', 'transacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_savings_transactions');
        Schema::dropIfExists('student_savings_accounts');
    }
};
