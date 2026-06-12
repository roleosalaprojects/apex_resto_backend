<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zreadings', function (Blueprint $table) {
            $table->double('vatable_on_refunds', 15, 2)->default(0)->after('vat_on_refunds');
            $table->double('vat_exempt_on_refunds', 15, 2)->default(0)->after('vatable_on_refunds');
            $table->double('zero_rated_on_refunds', 15, 2)->default(0)->after('vat_exempt_on_refunds');
        });

        Schema::table('shift_readings', function (Blueprint $table) {
            $table->decimal('vatable_on_refunds', 12, 2)->default(0)->after('vat_on_refunds');
            $table->decimal('vat_exempt_on_refunds', 12, 2)->default(0)->after('vatable_on_refunds');
            $table->decimal('zero_rated_on_refunds', 12, 2)->default(0)->after('vat_exempt_on_refunds');
        });
    }

    public function down(): void
    {
        Schema::table('zreadings', function (Blueprint $table) {
            $table->dropColumn(['vatable_on_refunds', 'vat_exempt_on_refunds', 'zero_rated_on_refunds']);
        });

        Schema::table('shift_readings', function (Blueprint $table) {
            $table->dropColumn(['vatable_on_refunds', 'vat_exempt_on_refunds', 'zero_rated_on_refunds']);
        });
    }
};
