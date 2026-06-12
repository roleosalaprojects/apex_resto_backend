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
        Schema::table('zreadings', function (Blueprint $table) {
            $table->string('refund_first_or')->nullable()->after('last_or');
            $table->string('refund_last_or')->nullable()->after('refund_first_or');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zreadings', function (Blueprint $table) {
            $table->dropColumn('refund_first_or');
           $table->dropColumn('refund_last_or');
        });
    }
};
