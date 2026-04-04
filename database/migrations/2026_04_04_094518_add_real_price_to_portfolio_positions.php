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
            $table->decimal('current_price', 20, 8)
                ->nullable()
                ->after('target_price');

            $table->timestamp('last_price_updated_at')
                ->nullable()
                ->after('current_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_positions', function (Blueprint $table) {
            $table->dropColumn([
                'current_price',
                'last_price_updated_at',
            ]);
        });
    }
};
