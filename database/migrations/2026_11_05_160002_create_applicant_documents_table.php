<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('original_name', 255);
            $table->string('storage_path', 500);
            $table->string('disk', 30)->default('private');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
            $table->unique(['applicant_id', 'document_type']);
        });
    }

    public function down(): void { Schema::dropIfExists('applicant_documents'); }
};
