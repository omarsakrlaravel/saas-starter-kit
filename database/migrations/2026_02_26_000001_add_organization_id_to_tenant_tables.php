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
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('user_id')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::table('api_keys', function (Blueprint $table): void {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('user_id')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('api_keys', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
