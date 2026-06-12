<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('approved_at');
            $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
            $table->string('void_reason', 500)->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
        });
    }
};
