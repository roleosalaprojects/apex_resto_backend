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
            $table->string('special_discount_name')->nullable()->after('discount');
            $table->string('special_discount_id')->nullable();
            $table->string('special_discount_tin')->nullable();
            $table->string('special_discount_child_name')->nullable();
            $table->string('special_discount_child_birthdate')->nullable();
            $table->string('special_discount_child_age')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('special_discount_name');
            $table->dropColumn('special_discount_id');
            $table->dropColumn('special_discount_tin');
            $table->dropColumn('special_discount_child_name');
            $table->dropColumn('special_discount_child_birthdate');
            $table->dropColumn('special_discount_child_age');
        });
    }
};
