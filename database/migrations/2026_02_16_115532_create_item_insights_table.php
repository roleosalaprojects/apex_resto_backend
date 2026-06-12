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
        Schema::create('item_insights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('insight_date');
            $table->unsignedBigInteger('item_id');
            $table->unsignedInteger('rank');
            $table->decimal('sellability_score', 8, 2);
            $table->json('score_breakdown');
            $table->text('ai_insight')->nullable();
            $table->decimal('predicted_qty', 15, 2);
            $table->decimal('current_stock', 15, 2)->nullable();
            $table->decimal('profit_margin', 8, 2)->nullable();
            $table->string('category_name')->nullable();
            $table->json('factors')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'store_id', 'insight_date', 'item_id'], 'item_insights_unique');
            $table->index(['user_id', 'store_id', 'insight_date', 'rank'], 'item_insights_rank_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_insights');
    }
};
