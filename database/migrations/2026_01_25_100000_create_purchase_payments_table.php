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
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
            $table->foreignId('bank_id')->constrained('banks')->onDelete('restrict');
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->onDelete('restrict');
            $table->double('amount', 15, 2);
            $table->date('payment_date');
            // Payment methods: 1=Cash, 2=Check, 3=Bank Transfer, 4=E-Wallet
            $table->tinyInteger('payment_method');
            $table->string('check_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_id', 'payment_date']);
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};
