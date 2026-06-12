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
        Schema::table('ecommerce_order_status_changes', function (Blueprint $table) {
            $table->timestamp('sms_notified_at')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_order_status_changes', function (Blueprint $table) {
            $table->dropColumn('sms_notified_at');
        });
    }
};
