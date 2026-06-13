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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->unsignedInteger('party_size')->default(1);
            $table->dateTime('reserved_at');
            $table->unsignedInteger('duration_minutes')->default(90);
            $table->unsignedBigInteger('table_id')->nullable()->index();
            // pending, confirmed, seated, completed, no_show, cancelled
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->integer('user_id')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'reserved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
