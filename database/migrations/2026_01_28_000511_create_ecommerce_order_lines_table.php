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
        Schema::create('ecommerce_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ecommerce_order_id');
            $table->unsignedBigInteger('item_id');
            $table->string('item_name');
            $table->integer('qty');
            $table->decimal('price', 12, 2);
            $table->decimal('sub_total', 12, 2);
            $table->timestamps();

            $table->foreign('ecommerce_order_id')->references('id')->on('ecommerce_orders')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_lines');
    }
};
