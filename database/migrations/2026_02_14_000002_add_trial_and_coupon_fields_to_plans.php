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
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'trial_days')) {
                $table->unsignedInteger('trial_days')->nullable()->after('onetime_price');
            }

            if (! Schema::hasColumn('plans', 'stripe_coupon_id')) {
                $table->string('stripe_coupon_id')->nullable()->after('trial_days');
            }

            if (! Schema::hasColumn('plans', 'stripe_promotion_code')) {
                $table->string('stripe_promotion_code')->nullable()->after('stripe_coupon_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('plans', 'stripe_promotion_code')) {
                $columns[] = 'stripe_promotion_code';
            }

            if (Schema::hasColumn('plans', 'stripe_coupon_id')) {
                $columns[] = 'stripe_coupon_id';
            }

            if (Schema::hasColumn('plans', 'trial_days')) {
                $columns[] = 'trial_days';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
