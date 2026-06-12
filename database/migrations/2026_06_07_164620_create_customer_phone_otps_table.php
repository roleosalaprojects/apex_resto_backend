<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short-lived OTP store for customer phone verification at /shop
 * registration. One row per code issued; the code itself is stored
 * hashed (SHA-256 — fast equality, no need for bcrypt's slow path
 * since codes are throwaway and the table TTL is minutes).
 *
 *   phone        — the recipient, stored in canonical local PH form (09XXXXXXXXX)
 *   code_hash    — sha256 of the 6-digit code we sent
 *   attempts     — how many times someone tried to verify this code
 *   expires_at   — short — 10 minutes by default
 *   consumed_at  — set when verify() succeeds; subsequent attempts are rejected
 *   ip_address   — issuer's IP so we can detect floods
 *   sms_id       — VeroSMS's response id, useful for delivery debugging
 *
 * Pruning is a follow-up — garbage rows >24h old are safe to delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_phone_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('sms_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['phone', 'created_at'], 'cpo_phone_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_phone_otps');
    }
};
