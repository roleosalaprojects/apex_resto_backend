<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->unsigned()->nullable(); // Number assigned to an account
            $table->text('name')->nullable();
            $table->text('description')->nullable();
            $table->double('starting_balance', 15, 2)->nullable();
            $table->double('current_balance', 15, 2)->nullable();
            $table->integer('type')->unsigned()->nullable(); // 1 = Asset, 2 = Liability, 3 = Equity, 4 = Revenue, 5 = Expenses
            $table->integer('user_id')->unsigned()->nullable(); // Created by which user
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accounts');
    }
}
