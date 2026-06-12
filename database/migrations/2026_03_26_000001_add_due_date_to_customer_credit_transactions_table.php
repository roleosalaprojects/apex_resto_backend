<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customer_credit_transactions', 'due_date')) {
            return;
        }

        Schema::table('customer_credit_transactions', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('notes');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('customer_credit_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('customer_credit_transactions', 'due_date')) {
                $table->dropIndex(['due_date']);
                $table->dropColumn('due_date');
            }
        });
    }
};
