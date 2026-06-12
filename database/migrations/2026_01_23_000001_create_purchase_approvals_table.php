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
        // Create purchase_approvals table
        Schema::create('purchase_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_comment')->nullable();
            $table->timestamps();

            $table->index(['purchase_id', 'status']);
        });

        // Add approval_status to purchases table
        // 0 = Draft, 1 = Pending Approval, 2 = Approved, 3 = Rejected
        Schema::table('purchases', function (Blueprint $table) {
            $table->tinyInteger('approval_status')->default(1)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_approvals');

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('approval_status');
        });
    }
};
