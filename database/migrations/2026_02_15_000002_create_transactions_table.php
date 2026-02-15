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
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_id')->unique()->index();
            $table->string('stripe_customer_id')->index();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->integer('amount');
            $table->string('currency', 3);
            $table->string('status')->index();
            $table->string('payment_method_type')->nullable();
            $table->string('payment_method_last4')->nullable();
            $table->string('payment_method_brand')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->integer('refunded_amount')->default(0);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['billable_type', 'billable_id']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
