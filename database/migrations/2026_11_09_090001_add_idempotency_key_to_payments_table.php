<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('idempotency_key', 80)->nullable()->unique()->after('transaction_number');
        });

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->unique(['payment_id', 'type'], 'cash_transactions_payment_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropUnique('cash_transactions_payment_type_unique');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
