<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('featured')->default(false)->after('status');
            $table->unsignedInteger('featured_order')->nullable()->after('featured');
            $table->index(['featured', 'featured_order'], 'categories_featured_idx');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_featured_idx');
            $table->dropColumn(['featured', 'featured_order']);
        });
    }
};
