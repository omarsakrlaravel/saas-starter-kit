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
        Schema::create('promotion_codes', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_id')->unique()->index();
            $table->unsignedBigInteger('coupon_id');
            $table->string('code')->index();
            $table->boolean('active')->default(true);
            $table->integer('max_redemptions')->nullable();
            $table->integer('times_redeemed')->default(0);
            $table->boolean('first_time_transaction')->default(false);
            $table->integer('minimum_amount')->nullable();
            $table->string('minimum_amount_currency')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('coupon_id')
                ->references('id')
                ->on('coupons')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_codes');
    }
};
