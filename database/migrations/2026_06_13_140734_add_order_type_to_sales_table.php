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
        Schema::table('sales', function (Blueprint $table) {
            // 0 dine-in, 1 take-out (default), 2 delivery
            $table->unsignedTinyInteger('order_type')->default(1)->after('type');
            $table->unsignedBigInteger('table_id')->nullable()->after('order_type');
            $table->unsignedInteger('pax')->nullable()->after('table_id');
            $table->unsignedInteger('sc_count')->nullable()->after('pax');
            $table->unsignedInteger('pwd_count')->nullable()->after('sc_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'table_id', 'pax', 'sc_count', 'pwd_count']);
        });
    }
};
