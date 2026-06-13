<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Restaurant module permission family (tables, kitchen stations,
     * reservations admin screens), mirroring the existing per-feature
     * read/create/update/delete flag convention.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('rstrnt')->default(false);
            $table->boolean('rstrnt_read')->default(false);
            $table->boolean('rstrnt_create')->default(false);
            $table->boolean('rstrnt_update')->default(false);
            $table->boolean('rstrnt_delete')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['rstrnt', 'rstrnt_read', 'rstrnt_create', 'rstrnt_update', 'rstrnt_delete']);
        });
    }
};
