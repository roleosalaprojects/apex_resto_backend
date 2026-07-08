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
        Schema::table('items', function (Blueprint $table) {
            // Whether the item appears on POS/waiter menus. Recipe
            // ingredients and other stock-only items set this false; they
            // stay active for purchasing, recipes and stock without being
            // orderable. Defaults true so existing catalogs are unchanged.
            $table->boolean('show_in_pos')->default(true)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('show_in_pos');
        });
    }
};
