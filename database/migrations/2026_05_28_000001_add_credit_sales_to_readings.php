<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zreadings', function (Blueprint $table) {
            $table->double('credit_sales', 15, 2)->default(0)->after('e_wallet');
        });

        Schema::table('shift_readings', function (Blueprint $table) {
            $table->decimal('credit_sales', 12, 2)->default(0)->after('e_wallet_sales');
        });
    }

    public function down(): void
    {
        Schema::table('zreadings', function (Blueprint $table) {
            $table->dropColumn('credit_sales');
        });

        Schema::table('shift_readings', function (Blueprint $table) {
            $table->dropColumn('credit_sales');
        });
    }
};
