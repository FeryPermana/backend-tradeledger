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
        Schema::create('portfolio_positions', function (Blueprint $table) {
             $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();

            $table->decimal('quantity', 20, 8)->default(0);
            $table->decimal('avg_price', 20, 8)->default(0);
            $table->decimal('target_price', 20, 8)->nullable();

            $table->string('horizon')->nullable();
            $table->string('conviction_level')->nullable();
            $table->text('thesis')->nullable();
            $table->text('notes')->nullable();
            $table->index('conviction_level');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_positions');
    }
};
