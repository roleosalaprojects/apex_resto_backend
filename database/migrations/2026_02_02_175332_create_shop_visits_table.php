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
        Schema::create('shop_visits', function (Blueprint $table) {
            $table->id();

            // Session tracking
            $table->string('session_id')->index();
            $table->uuid('visitor_id')->index();

            // Customer (if logged in)
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');

            // Basic info
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('page_visited');
            $table->string('page_type')->default('browse'); // browse, product, cart, checkout

            // Referrer
            $table->text('referrer')->nullable();
            $table->string('referrer_domain')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();

            // Device info
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->string('browser')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('platform')->nullable(); // Windows, iOS, Android, etc.

            // Product tracking
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('items')->onDelete('set null');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');

            // Actions
            $table->string('action')->nullable(); // view, add_to_cart, remove_from_cart, search
            $table->json('action_data')->nullable(); // search query, quantity, etc.

            // Time tracking
            $table->integer('time_on_page')->nullable(); // seconds
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();

            // Geo (optional - can be filled by IP lookup)
            $table->string('country')->nullable();
            $table->string('city')->nullable();

            $table->timestamps();

            // Indexes for analytics
            $table->index(['created_at']);
            $table->index(['page_type', 'created_at']);
            $table->index(['device_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_visits');
    }
};
