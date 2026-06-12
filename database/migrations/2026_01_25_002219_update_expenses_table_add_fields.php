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
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('reference_number')->unique()->after('id');
            $table->foreignId('expense_category_id')->nullable()->after('reference_number')->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->after('expense_category_id')->constrained('stores')->nullOnDelete();
            $table->foreignId('bank_id')->after('store_id')->constrained('banks')->cascadeOnDelete();
            $table->foreignId('bank_transaction_id')->nullable()->after('bank_id')->constrained('bank_transactions')->nullOnDelete();
            $table->string('payee')->after('bank_transaction_id');
            $table->double('amount', 15, 2)->after('payee');
            $table->date('expense_date')->after('amount');
            $table->text('description')->nullable()->after('expense_date');
            $table->string('receipt_number')->nullable()->after('description');
            $table->tinyInteger('status')->default(1)->after('receipt_number'); // 1=active, 0=voided
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->softDeletes();

            // Indexes for faster queries
            $table->index(['store_id', 'expense_date']);
            $table->index(['bank_id', 'expense_date']);
            $table->index('expense_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['store_id']);
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['bank_transaction_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['approved_by']);

            $table->dropIndex(['store_id', 'expense_date']);
            $table->dropIndex(['bank_id', 'expense_date']);
            $table->dropIndex(['expense_category_id']);

            $table->dropColumn([
                'reference_number',
                'expense_category_id',
                'store_id',
                'bank_id',
                'bank_transaction_id',
                'payee',
                'amount',
                'expense_date',
                'description',
                'receipt_number',
                'status',
                'created_by',
                'approved_by',
                'approved_at',
                'deleted_at',
            ]);
        });
    }
};
