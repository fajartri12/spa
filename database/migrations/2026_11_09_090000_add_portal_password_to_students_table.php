<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('portal_password')->nullable()->after('entry_year');
            $table->dateTime('portal_password_set_at')->nullable()->after('portal_password');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['portal_password', 'portal_password_set_at']);
        });
    }
};
