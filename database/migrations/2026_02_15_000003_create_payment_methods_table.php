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
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_id')->unique()->index();
            $table->string('stripe_customer_id')->index();
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->string('type');
            $table->string('brand')->nullable();
            $table->string('last4')->nullable();
            $table->integer('exp_month')->nullable();
            $table->integer('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['billable_type', 'billable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
