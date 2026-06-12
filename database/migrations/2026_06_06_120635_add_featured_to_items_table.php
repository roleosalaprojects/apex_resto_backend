<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Note: items already has a `priority` boolean owned by the
            // SuperAdmin Priority Items dashboard surface. `featured` is
            // a separate, storefront-only concern — do not overload.
            $table->boolean('featured')->default(false)->after('priority');
            $table->unsignedInteger('featured_order')->nullable()->after('featured');
            $table->index(['featured', 'featured_order'], 'items_featured_idx');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_featured_idx');
            $table->dropColumn(['featured', 'featured_order']);
        });
    }
};
