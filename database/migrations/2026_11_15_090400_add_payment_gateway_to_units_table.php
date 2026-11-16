<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan payment gateway per unit pendidikan.
 *
 * Satu madrasah dapat memiliki unit dengan rekening/merchant berbeda, sehingga
 * penyedia dan kanal dipilih per unit. Bila kosong, aplikasi memakai nilai
 * default pada `config/payment_gateway.php` atau tetap pada verifikasi manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->string('payment_provider', 30)->nullable()->after('level');
            $table->string('payment_channel', 30)->nullable()->after('payment_provider');
            $table->boolean('payment_gateway_enabled')->default(false)->after('payment_channel');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn(['payment_provider', 'payment_channel', 'payment_gateway_enabled']);
        });
    }
};
