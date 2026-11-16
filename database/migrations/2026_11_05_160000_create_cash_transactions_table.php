<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_number', 60)->unique();
            $table->dateTime('transacted_at')->index();
            $table->string('type', 20)->index();
            $table->decimal('amount', 15, 2);
            $table->string('description', 255);
            $table->string('reference_number', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['account_id', 'transacted_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('cash_transactions'); }
};
