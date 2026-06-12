<?php

namespace App\Http\Controller\Database;

use Illuminate\Database\Schema\Blueprint;

class DBHelpers {
    public function addBillsAndCentsDenomination(Blueprint $table): void
    {
        $table->integer('one_thousand')->nullable()->default(0);
        $table->integer('five_hundred')->nullable()->default(0);
        $table->integer('two_hundred')->nullable()->default(0);
        $table->integer('one_hundred')->nullable()->default(0);
        $table->integer('fifty')->nullable()->default(0);
        $table->integer('twenty')->nullable()->default(0);
        $table->integer('ten')->nullable()->default(0);
        $table->integer('five')->nullable()->default(0);
        $table->integer('one')->nullable()->default(0);
        $table->double('centavos', 15, 2)->nullable()->default(0);
    }
}