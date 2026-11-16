<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penggajian / honorarium guru & karyawan.
 *
 * Satu periode (mis. "Honorarium November 2026") memiliki banyak slip
 * (`payroll_items`). Saat periode disetujui, satu transaksi kas keluar dan satu
 * jurnal batch dibentuk untuk seluruh slip sehingga rekonsiliasi bank cukup
 * mencocokkan satu transfer, bukan puluhan transfer terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();

            $table->string('period', 20)->index();  // format Y-m
            $table->string('title', 160);
            $table->string('description', 500)->nullable();

            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();

            // Akun kas/bank sumber dan akun beban tujuan pada bagan akun.
            $table->foreignId('cash_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();

            $table->string('status', 20)->default('draft')->index();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('employee_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();

            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('cash_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Satu periode hanya boleh ada sekali per judul+unit.
            $table->unique(['period', 'unit_id', 'title'], 'payroll_periods_unique');
        });

        Schema::create('payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();

            // Penerima: pegawai terdaftar, atau nama bebas untuk pembayaran
            // insentif komite yang bukan staf tetap.
            $table->foreignId('staff_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name', 120);
            $table->string('recipient_position', 80)->nullable();
            $table->string('recipient_account', 60)->nullable();
            $table->string('recipient_bank', 80)->nullable();

            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('deduction_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);

            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['payroll_period_id', 'staff_profile_id'], 'payroll_items_unique_staff');
            $table->index(['payroll_period_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_periods');
    }
};
