<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan digital (Virtual Account / QRIS dinamis) per tagihan siswa.
 *
 * Satu `student_bill` dapat memiliki beberapa intent (mis. VA kedaluwarsa lalu
 * dibuat ulang), tetapi hanya satu yang boleh berstatus `pending` pada satu
 * waktu. Pembayaran yang masuk lewat webhook membuat baris `payments` dan
 * mengalokasikannya ke tagihan, sehingga alur verifikasi manual tidak lagi
 * dipakai untuk transaksi non-tunai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('student_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();

            $table->string('provider', 30)->index();
            $table->string('channel', 30)->nullable();
            $table->string('reference', 60)->unique();

            // Nomor VA / payload QRIS yang ditampilkan ke pembayar.
            $table->string('pay_code', 100)->nullable()->index();
            $table->string('qr_url', 500)->nullable();
            $table->text('qr_string')->nullable();

            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->default(0);

            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable();

            // Jejak provider: id transaksi di sisi penyedia dan payload webhook
            // terakhir yang diterima (untuk audit bila terjadi sengketa).
            $table->string('provider_reference', 120)->nullable()->index();
            $table->json('provider_payload')->nullable();

            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status']);
            $table->index(['student_bill_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            // Menandai pembayaran yang dibuat otomatis oleh webhook gateway.
            $table->foreignId('payment_intent_id')->nullable()->after('idempotency_key')
                ->constrained('payment_intents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['payment_intent_id']);
            $table->dropColumn('payment_intent_id');
        });

        Schema::dropIfExists('payment_intents');
    }
};
