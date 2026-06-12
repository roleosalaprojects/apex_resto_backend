<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flag items as "priority" for the upcoming priority-items dashboard
     * feature: a curated list of items the owner wants to watch closely,
     * surfaced on the admin dashboard with a live sales count.
     *
     * The column does NOT participate in BIR reporting, e-journal output,
     * z-reading aggregation, or the existing /superadmin/adjustment flow.
     * It is purely a display/tracking flag for the dashboard surface.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->boolean('priority')->default(false)->after('low_stock_threshold');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropIndex(['priority']);
            $table->dropColumn('priority');
        });
    }
};
