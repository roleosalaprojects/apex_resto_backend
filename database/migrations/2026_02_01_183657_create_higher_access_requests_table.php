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
        Schema::create('higher_access_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('user_name');
            $table->unsignedBigInteger('store_id');
            $table->string('store_name');
            $table->unsignedBigInteger('pos_id');
            $table->string('pos_name');
            $table->enum('permission_type', ['discounts', 'refunds', 'delete_items']);
            $table->json('context_data')->nullable();
            $table->string('device_id');
            $table->enum('status', ['pending', 'approved', 'denied', 'cancelled', 'expired'])->default('pending');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('approver_name')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('pos_id')->references('id')->on('pos')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['store_id', 'status']);
            $table->index(['request_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('higher_access_requests');
    }
};
