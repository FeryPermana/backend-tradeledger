<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('category', 20)->default('stock')->after('market');
            $table->boolean('is_watchlist')->default(false)->after('category');
            $table->string('tradingview_url')->nullable()->after('is_watchlist');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'is_watchlist',
                'tradingview_url',
            ]);
        });
    }
};
