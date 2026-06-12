<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the schema pieces for the locked-unit feature:
     *
     * - item_units.locked: when true, this UoM cannot be sold at POS
     *   without a runtime higher-access approval (permission_type=locked_unit).
     * - roles.unit_lock: gates the web-admin toggle that flips item_units.locked.
     * - roles.unit_lock_approve: gates who can approve a locked_unit higher-access
     *   request (FCM recipient list + canApprove check).
     */
    public function up(): void
    {
        Schema::table('item_units', function (Blueprint $table): void {
            $table->boolean('locked')->default(false)->after('status');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('unit_lock')->default(false)->after('crdt_pymnt');
            $table->boolean('unit_lock_approve')->default(false)->after('unit_lock');
        });
    }

    public function down(): void
    {
        Schema::table('item_units', function (Blueprint $table): void {
            $table->dropColumn('locked');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['unit_lock', 'unit_lock_approve']);
        });
    }
};
