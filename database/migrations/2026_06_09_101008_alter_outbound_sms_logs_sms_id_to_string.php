<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VeroSMS returns integer message ids; SMS Gate returns ULID-shaped
     * strings. To support both relays behind the SmsRelayContract we
     * widen sms_id to varchar — existing integer rows survive the
     * MySQL conversion lossless-ly (we just gain the ability to hold
     * the new format).
     */
    public function up(): void
    {
        Schema::table('outbound_sms_logs', function (Blueprint $table) {
            $table->string('sms_id', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Going back means losing any SMS Gate ids that don't fit in
        // an unsigned int. Truncate string ids to null on the way down
        // so the column conversion doesn't blow up.
        \DB::table('outbound_sms_logs')
            ->whereNotNull('sms_id')
            ->where('sms_id', 'NOT REGEXP', '^[0-9]+$')
            ->update(['sms_id' => null]);

        Schema::table('outbound_sms_logs', function (Blueprint $table) {
            $table->unsignedInteger('sms_id')->nullable()->change();
        });
    }
};
