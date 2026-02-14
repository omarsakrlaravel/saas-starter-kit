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
        if (! Schema::hasTable('subscriptions') || ! Schema::hasColumn('subscriptions', 'plan_id')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropForeign(['plan_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->unsignedInteger('plan_id')->nullable()->change();
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('subscriptions') || ! Schema::hasColumn('subscriptions', 'plan_id')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropForeign(['plan_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->unsignedInteger('plan_id')->nullable(false)->change();
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
        });
    }
};
