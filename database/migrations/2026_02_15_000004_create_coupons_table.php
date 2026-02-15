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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_id')->unique()->index();
            $table->string('name')->nullable();
            $table->integer('amount_off')->nullable();
            $table->decimal('percent_off', 5, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('duration');
            $table->integer('duration_in_months')->nullable();
            $table->integer('max_redemptions')->nullable();
            $table->integer('times_redeemed')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('valid')->default(true);
            $table->timestamp('redeem_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
