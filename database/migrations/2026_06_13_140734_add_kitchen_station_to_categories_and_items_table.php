<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Category holds the default routing for items in it; an item-level
     * override wins when set (see KitchenRoutingService::resolveStation).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('kitchen_station_id')->nullable()->index()->after('id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('kitchen_station_id')->nullable()->index()->after('uom_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('kitchen_station_id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('kitchen_station_id');
        });
    }
};
