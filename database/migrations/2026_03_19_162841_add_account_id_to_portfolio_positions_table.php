<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_positions', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            $table->decimal('total_fees', 20, 8)
                ->default(0)
                ->after('avg_price');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn('total_fees');
        });
    }
};
