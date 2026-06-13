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
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('number')->nullable();
            $table->string('area')->nullable();
            $table->unsignedInteger('seats')->default(2);
            // 0 available, 1 occupied, 2 reserved, 3 inactive
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->integer('user_id')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
