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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop the unique index first since it depends on columns being removed.
            if (Schema::hasIndex('subscriptions', 'subscriptions_vendor_slug_vendor_subscription_id_unique')) {
                $table->dropUnique('subscriptions_vendor_slug_vendor_subscription_id_unique');
            }

            $columnsToDrop = [
                'vendor_slug',
                'vendor_product_id',
                'vendor_transaction_id',
                'vendor_customer_id',
                'vendor_subscription_id',
                'status',
                'seats',
                'cancel_url',
                'update_url',
                'cancelled_at',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('vendor_slug')->nullable();
            $table->string('vendor_product_id')->nullable();
            $table->string('vendor_transaction_id')->nullable();
            $table->string('vendor_customer_id')->nullable();
            $table->string('vendor_subscription_id')->nullable();
            $table->string('status')->default('active');
            $table->integer('seats')->default(1);
            $table->string('cancel_url')->nullable();
            $table->string('update_url')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unique(['vendor_slug', 'vendor_subscription_id'], 'subscriptions_vendor_slug_vendor_subscription_id_unique');
        });
    }
};
