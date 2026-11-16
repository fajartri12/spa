<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import mutasi rekening koran dan hasil pencocokannya.
 *
 * `bank_statement_imports` menyimpan berkas asal beserta ringkasan hasil,
 * sedangkan `bank_statement_lines` menyimpan satu baris mutasi. Pencocokan
 * mengacu pada `payments.transaction_number` atau `payment_intents.pay_code`
 * yang ditulis pada kolom berita/keterangan mutasi.
 *
 * `bank_statement_profiles` menyimpan pemetaan kolom per bank sehingga format
 * BSI, BRI, dan Bank Jatim dapat diimpor tanpa mengubah kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('bank_name', 80)->nullable();

            // Baris header pada berkas (0 = baris pertama).
            $table->unsignedSmallInteger('header_row')->default(0);
            $table->string('delimiter', 5)->default(';');

            // Nama kolom pada berkas, dipetakan ke kolom internal.
            $table->string('column_date', 60)->nullable();
            $table->string('column_description', 60)->nullable();
            $table->string('column_reference', 60)->nullable();
            $table->string('column_debit', 60)->nullable();
            $table->string('column_credit', 60)->nullable();
            $table->string('column_amount', 60)->nullable();
            $table->string('column_balance', 60)->nullable();

            // Format nilai pada berkas: tanggal dan pemisah angka.
            $table->string('date_format', 20)->default('d/m/Y');
            $table->string('decimal_separator', 3)->default(',');
            $table->string('thousands_separator', 3)->default('.');

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_statement_profile_id')->nullable()
                ->constrained('bank_statement_profiles')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('file_name', 255);
            $table->string('file_path', 500)->nullable();
            $table->string('file_hash', 64)->nullable()->index();

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('matched_rows')->default(0);
            $table->unsignedInteger('unmatched_rows')->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->decimal('total_debit', 15, 2)->default(0);

            $table->string('status', 20)->default('completed')->index();
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();

            $table->date('transacted_at')->index();
            $table->string('description', 500)->nullable();
            $table->string('reference', 120)->nullable()->index();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->nullable();

            // Baris ke berapa pada berkas asal, untuk penelusuran manual.
            $table->unsignedInteger('row_number')->default(0);

            $table->string('match_status', 20)->default('unmatched')->index();
            $table->string('match_rule', 30)->nullable();
            $table->decimal('match_confidence', 5, 2)->nullable();

            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('matched_at')->nullable();

            $table->timestamps();

            // Satu baris mutasi tidak boleh tercocokkan dua kali.
            $table->unique(['bank_statement_import_id', 'row_number'], 'bank_statement_lines_row_unique');
            $table->index(['match_status', 'transacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('bank_statement_profiles');
    }
};
