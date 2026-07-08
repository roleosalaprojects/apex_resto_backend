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
        Schema::create('order_tables', function (Blueprint $table) {
            $table->id();
            // Joined tables for one dine-in order (big parties spanning
            // several physical tables). orders.table_id stays the PRIMARY
            // table — receipts, transfers and legacy queries keep working;
            // this pivot holds every table the party occupies, primary
            // included, so seating/freeing logic reads one place.
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('table_id');
            $table->timestamps();

            $table->unique(['order_id', 'table_id']);
            $table->index('table_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_tables');
    }
};
