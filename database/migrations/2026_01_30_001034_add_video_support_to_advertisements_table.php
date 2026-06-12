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
        Schema::table('advertisements', function (Blueprint $table) {
            $table->enum('media_type', ['image', 'video'])->default('image')->after('image');
            $table->unsignedInteger('duration')->default(10)->after('media_type');
            $table->boolean('status')->default(true)->after('duration');
            $table->unsignedInteger('display_order')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'duration', 'status', 'display_order']);
        });
    }
};
