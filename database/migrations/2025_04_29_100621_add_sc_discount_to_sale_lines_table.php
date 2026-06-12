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
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->double('sc_discount', 15, 2)->nullable();
            $table->double('pwd_discount', 15, 2)->nullable();
            $table->double('sp_discount', 15, 2)->nullable();
            $table->double('naac_discount', 15, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropColumn('sc_discount');
            $table->dropColumn('pwd_discount');
            $table->dropColumn('sp_discount');
            $table->dropColumn('naac_discount');
        });
    }
};
