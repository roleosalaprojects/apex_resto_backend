<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-terminal BIR document series counters. txn_counter feeds the
     * Annex F "transaction number" stamped on every fiscal event; void
     * and return counters give voids/returns their own gapless series;
     * training_counter keeps training-mode transactions off the official
     * series while training_mode is the terminal-level toggle.
     */
    public function up(): void
    {
        Schema::table('pos', function (Blueprint $table) {
            $table->unsignedBigInteger('void_counter')->default(0)->after('reset_counter');
            $table->unsignedBigInteger('return_counter')->default(0)->after('void_counter');
            $table->unsignedBigInteger('txn_counter')->default(0)->after('return_counter');
            $table->unsignedBigInteger('training_counter')->default(0)->after('txn_counter');
            $table->boolean('training_mode')->default(false)->after('training_counter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos', function (Blueprint $table) {
            $table->dropColumn(['void_counter', 'return_counter', 'txn_counter', 'training_counter', 'training_mode']);
        });
    }
};
