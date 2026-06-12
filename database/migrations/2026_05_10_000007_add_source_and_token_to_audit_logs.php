<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Augment audit_logs so we can tell HOW an action got into the system:
     *
     *   source         'web' | 'openclaw' | 'mobile' | 'pos' | 'console' | null
     *   api_token_id   the api_tokens.id that authorised an openclaw call,
     *                  so we can attribute bot actions to a specific token.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('event');
            $table->unsignedBigInteger('api_token_id')->nullable()->after('source');

            $table->index('source', 'audit_logs_source_index');
            $table->index('api_token_id', 'audit_logs_api_token_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_source_index');
            $table->dropIndex('audit_logs_api_token_id_index');
            $table->dropColumn(['source', 'api_token_id']);
        });
    }
};
