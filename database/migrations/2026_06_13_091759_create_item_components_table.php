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
        Schema::create('item_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('component_item_id');
            $table->decimal('qty', 12, 4);
            $table->string('notes')->nullable();
            $table->integer('user_id')->index();
            $table->timestamps();

            $table->unique(['item_id', 'component_item_id']);
            $table->index('component_item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->foreign('component_item_id')->references('id')->on('items')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_components');
    }
};
