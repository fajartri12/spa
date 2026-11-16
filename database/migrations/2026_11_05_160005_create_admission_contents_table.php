<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 140)->unique();
            $table->string('title', 180);
            $table->longText('body');
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('admission_contents'); }
};
