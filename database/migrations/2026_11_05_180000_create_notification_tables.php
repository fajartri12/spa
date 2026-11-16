<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('channel', 20)->default('whatsapp')->index();
            $table->string('subject', 160)->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('channel', 20)->index();
            $table->string('recipient', 160);
            $table->string('status', 20)->default('pending')->index();
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reminder_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->string('name', 120);
            $table->integer('days_before_due')->default(3);
            $table->time('send_at')->default('08:00:00');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_schedules');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
};
