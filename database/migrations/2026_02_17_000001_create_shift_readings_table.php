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
        Schema::create('shift_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('z_reading_id')->nullable()->constrained('zreadings')->onDelete('set null');

            // Sales data (scoped to this shift)
            $table->decimal('cash_sales', 15, 2)->default(0);
            $table->decimal('e_wallet_sales', 15, 2)->default(0);
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->decimal('refunds', 15, 2)->default(0);
            $table->decimal('cash_in', 15, 2)->default(0);
            $table->decimal('cash_out', 15, 2)->default(0);

            // Denomination
            $table->integer('one_thousand')->default(0);
            $table->integer('five_hundred')->default(0);
            $table->integer('two_hundred')->default(0);
            $table->integer('one_hundred')->default(0);
            $table->integer('fifty')->default(0);
            $table->integer('twenty')->default(0);
            $table->integer('ten')->default(0);
            $table->integer('five')->default(0);
            $table->integer('one')->default(0);
            $table->decimal('centavos', 10, 2)->default(0);
            $table->decimal('denomination', 15, 2)->default(0);
            $table->decimal('discrepancy', 15, 2)->default(0);
            $table->decimal('total_cash', 15, 2)->default(0);

            $table->boolean('is_store_closure')->default(false);
            $table->timestamps();

            $table->index(['pos_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_readings');
    }
};
