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
        Schema::table('count_lines', function (Blueprint $table) {
            $table->decimal('counted_qty', 15, 2)->nullable()->after('unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('count_lines', function (Blueprint $table) {
            $table->dropColumn('counted_qty');
        });
    }
};
