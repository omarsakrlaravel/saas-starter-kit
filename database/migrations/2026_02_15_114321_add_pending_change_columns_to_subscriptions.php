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
            $table->unsignedInteger('pending_plan_id')->nullable()->after('cycle');
            $table->enum('pending_cycle', ['month', 'year'])->nullable()->after('pending_plan_id');
            $table->timestamp('pending_change_scheduled_at')->nullable()->after('pending_cycle');

            $table->foreign('pending_plan_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['pending_plan_id']);
            $table->dropColumn(['pending_plan_id', 'pending_cycle', 'pending_change_scheduled_at']);
        });
    }
};
