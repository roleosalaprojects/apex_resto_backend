<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // NULL = use the system default LOW_STOCK_THRESHOLD (10).
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('markup');
        });

        Schema::table('banks', function (Blueprint $table) {
            // NULL = no alert.
            $table->decimal('low_balance_threshold', 14, 2)->nullable()->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('low_balance_threshold');
        });
    }
};
