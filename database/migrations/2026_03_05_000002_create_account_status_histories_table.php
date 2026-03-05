<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('suspendable_type');
            $table->unsignedBigInteger('suspendable_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('reason')->nullable();
            $table->string('reference_code')->nullable();
            $table->json('details')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['suspendable_type', 'suspendable_id']);
            $table->index(['from_status', 'to_status']);
            $table->index('applied_by');

            $table->foreign('applied_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_status_histories');
    }
};
