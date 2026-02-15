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
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('stripe_id')->unique()->index();
            $table->string('stripe_customer_id')->index();
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('number')->nullable();
            $table->string('status')->index();
            $table->string('currency', 3);
            $table->integer('amount_due');
            $table->integer('amount_paid');
            $table->integer('amount_remaining');
            $table->integer('subtotal');
            $table->integer('tax')->nullable();
            $table->integer('total');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('hosted_invoice_url')->nullable();
            $table->text('invoice_pdf')->nullable();
            $table->json('line_items')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['billable_type', 'billable_id']);
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
