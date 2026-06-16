<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Annex F transaction number on every fiscal event so the e-journal
     * can be reconstructed across sales, readings and cash movements.
     */
    public function up(): void
    {
        Schema::table('xreadings', function (Blueprint $table) {
            $table->unsignedBigInteger('txn_no')->nullable()->after('id');
        });
        Schema::table('zreadings', function (Blueprint $table) {
            $table->unsignedBigInteger('txn_no')->nullable()->after('id');
        });
        Schema::table('pos_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('txn_no')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xreadings', function (Blueprint $table) {
            $table->dropColumn('txn_no');
        });
        Schema::table('zreadings', function (Blueprint $table) {
            $table->dropColumn('txn_no');
        });
        Schema::table('pos_logs', function (Blueprint $table) {
            $table->dropColumn('txn_no');
        });
    }
};
