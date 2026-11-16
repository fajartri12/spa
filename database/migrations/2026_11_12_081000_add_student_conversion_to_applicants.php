<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->foreignId('student_id')
                ->nullable()
                ->after('admission_period_id')
                ->constrained('students')
                ->nullOnDelete();
            $table->dateTime('converted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('student_id');
            $table->dropColumn('converted_at');
        });
    }
};
