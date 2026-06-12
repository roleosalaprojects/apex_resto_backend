<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->date('forecast_date');
            $table->string('forecast_type'); // daily_sales, item_demand, weekly_trend
            $table->decimal('predicted_value', 15, 2);
            $table->decimal('confidence', 5, 2)->default(0); // 0-100%
            $table->decimal('lower_bound', 15, 2)->nullable();
            $table->decimal('upper_bound', 15, 2)->nullable();
            $table->json('factors')->nullable(); // What influenced the prediction
            $table->text('ai_insight')->nullable(); // Natural language explanation from Ollama
            $table->json('historical_data')->nullable(); // Data used for prediction
            $table->timestamps();

            $table->index(['user_id', 'forecast_date', 'forecast_type']);
            $table->index(['item_id', 'forecast_date']);
        });

        Schema::create('reorder_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('store_id');
            $table->decimal('current_stock', 15, 2);
            $table->decimal('predicted_demand', 15, 2); // Next 7 days
            $table->decimal('suggested_quantity', 15, 2);
            $table->integer('days_until_stockout')->nullable();
            $table->string('urgency'); // low, medium, high, critical
            $table->text('ai_reason')->nullable();
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'urgency', 'is_acknowledged']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reorder_suggestions');
        Schema::dropIfExists('forecasts');
    }
};
