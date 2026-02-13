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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('current_organization_id')->nullable()->after('id');
            $table->index('current_organization_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_organization_id')
                ->references('id')
                ->on('organizations')
                ->nullOnDelete();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('cancel_url')->nullable();
            $table->string('update_url')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['cancel_url', 'update_url', 'cancelled_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_organization_id']);
            $table->dropIndex(['current_organization_id']);
            $table->dropColumn('current_organization_id');
        });
    }
};
