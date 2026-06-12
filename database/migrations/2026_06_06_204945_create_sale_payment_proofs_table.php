<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof-of-payment photos attached to a Sale (typically by the admin
 * recording a cashless payment — GCash screenshot, deposit slip, cheque
 * photo). Always optional. Multiple proofs per sale; uploaded_by tracks
 * which admin attached each photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();
            $table->string('path');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payment_proofs');
    }
};
