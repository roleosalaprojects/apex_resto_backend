<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The orders table is shared with the ecommerce flow; every new column
     * is nullable so existing ecommerce/admin order screens keep working.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 0 dine-in, 1 take-out, 2 delivery
            $table->unsignedTinyInteger('order_type')->nullable()->index()->after('status');
            $table->unsignedBigInteger('table_id')->nullable()->index()->after('order_type');
            $table->unsignedInteger('pax')->nullable()->after('table_id');
            $table->unsignedInteger('sc_count')->nullable()->after('pax');
            $table->unsignedInteger('pwd_count')->nullable()->after('sc_count');
            $table->unsignedBigInteger('waiter_id')->nullable()->index()->after('pwd_count');
            $table->string('guest_name')->nullable()->after('waiter_id');
            $table->unsignedBigInteger('store_id')->nullable()->index()->after('guest_name');
            $table->string('delivery_address')->nullable()->after('store_id');
            $table->string('delivery_contact')->nullable()->after('delivery_address');
            $table->string('delivery_status')->nullable()->after('delivery_contact');
            $table->text('notes')->nullable()->after('delivery_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_type', 'table_id', 'pax', 'sc_count', 'pwd_count',
                'waiter_id', 'guest_name', 'store_id', 'delivery_address',
                'delivery_contact', 'delivery_status', 'notes',
            ]);
        });
    }
};
