<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timestamp the moment we accepted an SMS OTP against the customer's
 * phone at registration. NULL for pre-existing customers (created
 * before phone verification was wired); they can verify later from
 * their profile if we want to expose it there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }
};
