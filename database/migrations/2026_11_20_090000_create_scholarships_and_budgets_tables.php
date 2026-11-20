<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_programs', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->string('sponsor', 120)->nullable();
            $table->string('funding_source', 20)->default('internal'); // internal, government, private
            $table->string('discount_type', 10); // percent, fixed
            $table->decimal('discount_value', 15, 2);
            $table->foreignId('payment_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quota')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('scholarship_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scholarship_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_discount_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['scholarship_program_id', 'student_id']);
        });

        Schema::table('student_discounts', function (Blueprint $table): void {
            $table->foreignId('scholarship_award_id')->nullable()->after('payment_category_id')->constrained()->nullOnDelete();
        });

        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('planned_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::table('student_discounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scholarship_award_id');
        });
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('scholarship_awards');
        Schema::dropIfExists('scholarship_programs');
    }
};
