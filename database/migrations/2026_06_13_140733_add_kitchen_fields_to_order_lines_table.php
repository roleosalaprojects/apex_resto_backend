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
        Schema::table('order_lines', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('order_id');
            $table->unsignedInteger('round')->default(1)->after('notes');
            $table->unsignedBigInteger('kitchen_station_id')->nullable()->index()->after('round');
            // 0 queued, 1 preparing, 2 ready, 3 served, 4 voided
            $table->unsignedTinyInteger('line_status')->default(0)->index()->after('kitchen_station_id');
            $table->timestamp('fired_at')->nullable()->after('line_status');
            $table->timestamp('ready_at')->nullable()->after('fired_at');
            $table->timestamp('served_at')->nullable()->after('ready_at');
            $table->unsignedBigInteger('bumped_by')->nullable()->after('served_at');
            $table->unsignedBigInteger('voided_by')->nullable()->after('bumped_by');
            $table->string('void_reason')->nullable()->after('voided_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn([
                'notes', 'round', 'kitchen_station_id', 'line_status',
                'fired_at', 'ready_at', 'served_at', 'bumped_by',
                'voided_by', 'void_reason',
            ]);
        });
    }
};
