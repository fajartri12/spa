<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
            $table->index(['payment_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('payment_verifications'); }
};
