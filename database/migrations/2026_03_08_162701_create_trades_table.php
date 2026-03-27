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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained();
            $table->foreignId('strategy_id')->nullable()->constrained();

            $table->enum('position_type', [
                'scalping',
                'intra_day',
                'swing',
                'investment'
            ]);

            $table->decimal('entry_price', 20, 8);
            $table->decimal('exit_price', 20, 8)->nullable();

            $table->decimal('quantity', 20, 8);
            $table->decimal('closed_quantity', 20, 8)->default(0);

            $table->decimal('stop_loss', 20, 8)->nullable();
            $table->decimal('take_profit', 20, 8)->nullable();

            $table->decimal('fees', 15, 2)->default(0);

            $table->decimal('profit_loss', 20, 2)->nullable();
            $table->decimal('r_multiple', 10, 2)->nullable();

            $table->timestamp('entry_date');
            $table->timestamp('exit_date')->nullable();
            $table->string('status')->default('open');

            $table->text('notes')->nullable();

            $table->index('entry_date');
            $table->index('asset_id');
            $table->index('strategy_id');
            $table->index('account_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
