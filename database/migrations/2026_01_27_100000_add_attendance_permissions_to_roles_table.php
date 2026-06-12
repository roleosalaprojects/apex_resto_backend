<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->tinyInteger('attndnc')->default(0)->after('spplrs_delete');
            $table->tinyInteger('attndnc_read')->default(0)->after('attndnc');
            $table->tinyInteger('attndnc_create')->default(0)->after('attndnc_read');
            $table->tinyInteger('attndnc_update')->default(0)->after('attndnc_create');
            $table->tinyInteger('attndnc_delete')->default(0)->after('attndnc_update');
            $table->tinyInteger('attndnc_schedules')->default(0)->after('attndnc_delete');
            // Banking
            $table->tinyInteger('bnkng')->default(0)->after('attndnc_schedules');
            $table->tinyInteger('bnkng_read')->default(0)->after('bnkng');
            $table->tinyInteger('bnkng_create')->default(0)->after('bnkng_read');
            $table->tinyInteger('bnkng_update')->default(0)->after('bnkng_create');
            $table->tinyInteger('bnkng_delete')->default(0)->after('bnkng_update');
            // Expenses
            $table->tinyInteger('expnss')->default(0)->after('bnkng_delete');
            $table->tinyInteger('expnss_read')->default(0)->after('expnss');
            $table->tinyInteger('expnss_create')->default(0)->after('expnss_read');
            $table->tinyInteger('expnss_update')->default(0)->after('expnss_create');
            $table->tinyInteger('expnss_delete')->default(0)->after('expnss_update');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn([
                'attndnc',
                'attndnc_read',
                'attndnc_create',
                'attndnc_update',
                'attndnc_delete',
                'attndnc_schedules',
            ]);
        });

        Schema::table('roles', function (Blueprint $table) {
            $columns = ['bnkng', 'bnkng_read', 'bnkng_create', 'bnkng_update', 'bnkng_delete',
                'expnss', 'expnss_read', 'expnss_create', 'expnss_update', 'expnss_delete'];

            $existing = array_filter($columns, fn ($col) => Schema::hasColumn('roles', $col));

            if (! empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
