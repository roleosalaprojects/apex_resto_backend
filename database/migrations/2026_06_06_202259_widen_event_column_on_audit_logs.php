<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_logs.event was originally an ENUM restricted to the eight
 * built-in trait events (created/updated/deleted/restored/voided/
 * refunded/approved/rejected). The service-layer audit trail for
 * admin-recorded cashless sales adds new event names — payment_recorded,
 * created_via_admin, cheque_cleared, cheque_bounced — that the ENUM
 * silently rewrote to '' on insert.
 *
 * Widening to a plain VARCHAR removes that footgun and keeps the column
 * extensible for future named events without another migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('event', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->enum('event', [
                'created', 'updated', 'deleted', 'restored',
                'voided', 'refunded', 'approved', 'rejected',
            ])->change();
        });
    }
};
