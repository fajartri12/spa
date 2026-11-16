<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kept as a no-op compatibility migration; billing columns are defined in create_bills_table.
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {         $table->dropColumn(['original_amount', 'discount_amount', 'total_amount', 'remaining_amount']); });
    }
};
