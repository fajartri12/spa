<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_savings_transactions', function (Blueprint $table): void {
            $table->string('status', 20)->default('posted')->after('type')->index();
            $table->foreignId('verified_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable()->after('verified_by');
            $table->string('rejection_reason')->nullable()->after('description');
        });

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->foreignId('student_savings_transaction_id')
                ->nullable()
                ->after('payment_id')
                ->constrained('student_savings_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('student_savings_transaction_id');
        });

        Schema::table('student_savings_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['status', 'verified_at', 'rejection_reason']);
        });
    }
};
