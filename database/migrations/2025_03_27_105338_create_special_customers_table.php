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
        Schema::create('special_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('identifier');
            $table->string('tin');
            $table->smallInteger('type')->default(0);
            $table->string('child_age')->nullable();
            $table->string('child_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_customers');
    }
};
