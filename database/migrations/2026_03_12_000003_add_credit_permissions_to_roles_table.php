<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('crdt_sale')->default(false)->after('csh_out');
            $table->boolean('crdt_pymnt')->default(false)->after('crdt_sale');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['crdt_sale', 'crdt_pymnt']);
        });
    }
};
