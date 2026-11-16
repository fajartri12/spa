<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_bills', function (Blueprint $table): void {
            $table->dateTime('paid_at')->nullable()->after('due_date');
        });

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->foreignId('admission_bill_id')
                ->nullable()
                ->after('student_savings_transaction_id')
                ->constrained('admission_bills')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('admission_bill_id');
        });

        Schema::table('admission_bills', function (Blueprint $table): void {
            $table->dropColumn('paid_at');
        });
    }
};
