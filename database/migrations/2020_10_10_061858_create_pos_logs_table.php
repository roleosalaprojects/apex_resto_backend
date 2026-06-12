<?php

use Illuminate\Cache\Lock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_logs', function (Blueprint $table) {
            $table->id();
            $table->double('cash_in', 15, 2)->nullable();
            $table->double('rendered', 15, 2)->nullable();
            $table->double('cash_out', 15, 2)->nullable();
            $table->integer('type');
            $table->string('reason')->nullable();
            $table->integer('so_id')->nullable();
            $table->integer('pos_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('user_id')->index('user_id');
            $table->timestamps();
            // For type and reason
            // 1:login
            // 2:store_selection
            // 3:start_day
            // 4:Cash-In
            // 5: Sales
            // 6: Refund
            // 7: Lock
            // 8: Log-Out
            // 9: Unlocked
            // 10: Print Z-Reading
            // 11: Print X-Reading
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_logs');
    }
}
