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
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('promotion_code_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->integer('discount_amount');
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->foreign('coupon_id')
                ->references('id')
                ->on('coupons')
                ->cascadeOnDelete();

            $table->foreign('promotion_code_id')
                ->references('id')
                ->on('promotion_codes')
                ->nullOnDelete();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->nullOnDelete();

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
