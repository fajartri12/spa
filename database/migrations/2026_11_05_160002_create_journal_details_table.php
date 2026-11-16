<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->index(['journal_id', 'account_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('journal_details'); }
};
