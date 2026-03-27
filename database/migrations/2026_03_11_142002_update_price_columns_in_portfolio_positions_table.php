<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('portfolio_positions', function (Blueprint $table) {
            $table->decimal('avg_price', 16, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_positions', function (Blueprint $table) {
            $table->decimal('avg_price', 8, 2)->change();
            $table->decimal('target_price', 8, 2)->nullable()->change();
        });
    }
};
