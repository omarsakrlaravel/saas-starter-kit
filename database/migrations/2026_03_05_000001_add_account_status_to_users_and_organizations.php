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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->default('active');
            $table->string('status_reason')->nullable();
            $table->timestamp('status_expires_at')->nullable();

            $table->index('status');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('status')->default('active');
            $table->string('status_reason')->nullable();
            $table->timestamp('status_expires_at')->nullable();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'status_reason', 'status_expires_at']);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'status_reason', 'status_expires_at']);
        });
    }
};
