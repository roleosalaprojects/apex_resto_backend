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
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_wholesale')->default(false)->after('status');
            $table->timestamp('wholesale_approved_at')->nullable()->after('is_wholesale');
            $table->unsignedBigInteger('wholesale_approved_by')->nullable()->after('wholesale_approved_at');

            $table->foreign('wholesale_approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['wholesale_approved_by']);
            $table->dropColumn(['is_wholesale', 'wholesale_approved_at', 'wholesale_approved_by']);
        });
    }
};
