<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('price_sync_enabled')
                ->default(false)
                ->after('remember_token');

            $table->json('price_sync_times')
                ->nullable()
                ->after('price_sync_enabled');
            // contoh: ["09:00", "21:00"]

            $table->timestamp('last_price_sync_at')
                ->nullable()
                ->after('price_sync_times');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'price_sync_enabled',
                'price_sync_times',
                'last_price_sync_at',
            ]);
        });
    }
};
