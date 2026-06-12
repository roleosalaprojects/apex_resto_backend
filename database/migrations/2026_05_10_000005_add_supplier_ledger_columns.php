<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Tagging an expense with a supplier lets it show up in that
            // supplier's ledger. Nullable because most expenses (utilities,
            // rent, payroll) aren't tied to a supplier.
            $table->unsignedBigInteger('supplier_id')->nullable()->after('store_id');
            $table->index('supplier_id', 'expenses_supplier_id_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            // Net N. NULL = no default terms set.
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_supplier_id_index');
            $table->dropColumn('supplier_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('payment_terms_days');
        });
    }
};
