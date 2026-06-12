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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->decimal('amount', 10, 2);
            $table->decimal('minimum_amount', 10, 2)->default(0);
            $table->integer('max_uses')->default(1);
            $table->integer('used_count')->default(0);
            $table->unsignedBigInteger('store_id')->nullable();
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('set null');
            $table->index(['code', 'is_active']);
            $table->index(['store_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
