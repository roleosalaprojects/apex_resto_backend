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
            $table->boolean('is_composite')->default(false)->index()->after('type');
            $table->boolean('cost_override')->default(false)->after('is_composite');
            $table->string('uom_label', 10)->nullable()->after('cost_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['is_composite']);
            $table->dropColumn(['is_composite', 'cost_override', 'uom_label']);
        });
    }
};
