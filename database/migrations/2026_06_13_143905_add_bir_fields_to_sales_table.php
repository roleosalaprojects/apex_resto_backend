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
            $table->unsignedBigInteger('txn_no')->nullable()->after('counter');
            $table->unsignedBigInteger('void_no')->nullable()->after('txn_no');
            $table->unsignedBigInteger('return_no')->nullable()->after('void_no');
            $table->boolean('is_training')->default(false)->index()->after('return_no');
            $table->unsignedInteger('reprint_count')->default(0)->after('is_training');
            $table->timestamp('last_reprinted_at')->nullable()->after('reprint_count');
            $table->unsignedBigInteger('last_reprinted_by')->nullable()->after('last_reprinted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['txn_no', 'void_no', 'return_no', 'is_training', 'reprint_count', 'last_reprinted_at', 'last_reprinted_by']);
        });
    }
};
