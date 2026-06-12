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
        Schema::table('item_units', function (Blueprint $table) {
            $table->index('item_id');
            $table->index('barcode');
        });

        Schema::table('items', function (Blueprint $table) {
           $table->index('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_units', function (Blueprint $table) {
            $table->dropIndex('item_units_item_id_index');
            $table->dropIndex('item_units_barcode_index');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_barcode_index');
        });
    }
};
