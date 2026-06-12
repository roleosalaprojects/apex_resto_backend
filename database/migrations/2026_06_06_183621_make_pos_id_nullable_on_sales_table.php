<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales recorded by web admin (cashless flow) have no POS terminal —
 * pos_id IS NULL is the signal that distinguishes admin-recorded sales
 * from POS-rung sales. The Z-Reading flow already filters on
 * pos_id = $reading->pos_id, so admin sales correctly stay out of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->integer('pos_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $nullPosCount = \DB::table('sales')->whereNull('pos_id')->count();
        if ($nullPosCount > 0) {
            throw new \RuntimeException(
                "Cannot make sales.pos_id NOT NULL: {$nullPosCount} sale(s) currently have pos_id = NULL."
            );
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->integer('pos_id')->nullable(false)->change();
        });
    }
};
