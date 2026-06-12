<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forensic record of every outbound SMS dispatch we attempted through
 * VeroSMS — successful AND failed. Lets an admin debug "the customer
 * says they never got the OTP" without having to grep the laravel log.
 *
 *   type             — short label, free text ('otp_register' today;
 *                      forward-compatible for order-status SMS,
 *                      marketing, etc.)
 *   sms_id           — VeroSMS's primary key (their /api/send/sms
 *                      response). NULL when send_failed before the
 *                      relay accepted it.
 *   vero_status_code — 1 delivered / 2 processing / 3 failed
 *                      (from /api/check/status). NULL until polled.
 *   status           — local lifecycle: 'sent'/'failed' on dispatch,
 *                      'delivered'/'processing'/'failed' once polled.
 *   message_length   — accounting + sanity-check (no PII / no code body)
 *   last_checked_at  — when we last hit /api/check/status for this row
 *   error            — short error message when status='failed'
 *
 * We do NOT store the SMS body — for OTPs that would be a code leak;
 * for any future template we'd rather not have an audit-log table full
 * of personal data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->index();
            $table->string('type', 32)->default('otp_register');
            $table->unsignedInteger('sms_id')->nullable()->index();
            $table->unsignedTinyInteger('vero_status_code')->nullable();
            $table->string('status', 16)->default('sent');
            $table->unsignedSmallInteger('message_length')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Two short composite indexes to keep the admin filter
            // queries snappy without hitting the 64-char ceiling.
            $table->index(['type', 'created_at'], 'osl_type_created_idx');
            $table->index(['status', 'created_at'], 'osl_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_sms_logs');
    }
};
