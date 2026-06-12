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
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('bank_id')->constrained('banks')->onDelete('cascade');
            $table->foreignId('transfer_to_bank_id')->nullable()->constrained('banks')->onDelete('set null');
            // Transaction types: 1=deposit, 2=withdrawal, 3=transfer_out, 4=transfer_in
            $table->tinyInteger('type');
            $table->double('amount', 15, 2);
            $table->double('balance_before', 15, 2);
            $table->double('balance_after', 15, 2);
            $table->string('description')->nullable();
            $table->string('payee')->nullable();
            $table->date('transaction_date');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bank_id', 'transaction_date']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
