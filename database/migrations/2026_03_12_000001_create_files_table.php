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
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->string('fileable_type')->nullable();
            $table->unsignedBigInteger('fileable_id')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('access_level')->default('private');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['fileable_type', 'fileable_id']);
            $table->unique(['disk', 'path']);
            $table->index('uploaded_by_user_id');
            $table->index('organization_id');

            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
