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
            $table->decimal('total_cash', 12, 2)->default(0)->after('centavos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zreadings', function (Blueprint $table) {
            $table->dropColumn('total_cash');
        });
    }
};
