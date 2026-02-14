<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'stripe_id')) {
                $table->string('stripe_id')->nullable()->index();
            }

            if (! Schema::hasColumn('users', 'pm_type')) {
                $table->string('pm_type')->nullable();
            }

            if (! Schema::hasColumn('users', 'pm_last_four')) {
                $table->string('pm_last_four', 4)->nullable();
            }
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscriptions', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('subscriptions', 'type')) {
                $table->string('type')->default('default')->after('user_id');
            }

            if (! Schema::hasColumn('subscriptions', 'stripe_id')) {
                $table->string('stripe_id')->nullable()->after('type');
            }

            if (! Schema::hasColumn('subscriptions', 'stripe_status')) {
                $table->string('stripe_status')->nullable()->after('stripe_id');
            }

            if (! Schema::hasColumn('subscriptions', 'stripe_price')) {
                $table->string('stripe_price')->nullable()->after('stripe_status');
            }

            if (! Schema::hasColumn('subscriptions', 'quantity')) {
                $table->integer('quantity')->nullable()->after('stripe_price');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('subscriptions', 'user_id')) {
                $table->index('user_id');
            }

            if (Schema::hasColumn('subscriptions', 'stripe_id')) {
                $table->index('stripe_id');
            }

            if (Schema::hasColumn('subscriptions', 'stripe_status')) {
                $table->index('stripe_status');
            }

            if (Schema::hasColumn('subscriptions', 'user_id')) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        if (Schema::hasTable('subscriptions')) {
            $subscriptions = DB::table('subscriptions')
                ->select(['id', 'billable_type', 'billable_id', 'vendor_subscription_id', 'status', 'seats', 'user_id'])
                ->get();

            foreach ($subscriptions as $subscription) {
                $userId = $subscription->user_id;

                if ($userId === null && $subscription->billable_type === 'user') {
                    $userId = $subscription->billable_id;
                }

                if ($userId === null && $subscription->billable_type === 'organization' && Schema::hasTable('organizations')) {
                    $userId = DB::table('organizations')
                        ->where('id', $subscription->billable_id)
                        ->value('owner_user_id');
                }

                $stripeStatus = match ($subscription->status) {
                    'active' => 'active',
                    'trialing' => 'trialing',
                    'past_due' => 'past_due',
                    'incomplete' => 'incomplete',
                    default => 'canceled',
                };

                DB::table('subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'user_id' => $userId,
                        'type' => 'default',
                        'stripe_id' => $subscription->vendor_subscription_id,
                        'stripe_status' => $stripeStatus,
                        'quantity' => $subscription->seats,
                    ]);
            }
        }

        if (! Schema::hasTable('subscription_items')) {
            Schema::create('subscription_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('subscription_id');
                $table->string('stripe_id');
                $table->string('stripe_product');
                $table->string('stripe_price');
                $table->integer('quantity')->nullable();
                $table->timestamps();

                $table->index(['subscription_id', 'stripe_price']);
                $table->unique(['stripe_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscription_items')) {
            Schema::dropIfExists('subscription_items');
        }

        Schema::table('subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('subscriptions', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('subscriptions', 'stripe_id')) {
                $table->dropIndex(['stripe_id']);
                $table->dropColumn('stripe_id');
            }

            if (Schema::hasColumn('subscriptions', 'stripe_status')) {
                $table->dropIndex(['stripe_status']);
                $table->dropColumn('stripe_status');
            }

            if (Schema::hasColumn('subscriptions', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('subscriptions', 'stripe_price')) {
                $table->dropColumn('stripe_price');
            }

            if (Schema::hasColumn('subscriptions', 'quantity')) {
                $table->dropColumn('quantity');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'stripe_id')) {
                $table->dropIndex(['stripe_id']);
                $table->dropColumn('stripe_id');
            }

            if (Schema::hasColumn('users', 'pm_type')) {
                $table->dropColumn('pm_type');
            }

            if (Schema::hasColumn('users', 'pm_last_four')) {
                $table->dropColumn('pm_last_four');
            }
        });
    }
};
