<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_logs', function (Blueprint $table) {
            $table->foreignId('shift_reading_id')->nullable()->after('so_id')
                ->constrained('shift_readings')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('pos_logs', function (Blueprint $table) {
            $table->dropForeign(['shift_reading_id']);
            $table->dropColumn('shift_reading_id');
        });
    }
};
