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
            $table->double('solo_parent')->default(10);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->double('naac')->default(20);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('solo_parent');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('naac');
        });
    }
};
