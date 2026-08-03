<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('announcement_id');
            $table->string('request_id');
            $table->string('package_id');
            $table->string('package_hash', 64);
            $table->string('request_hash', 64);
            $table->string('lineage_id')->nullable();
            $table->string('status');
            $table->string('model_id');
            $table->string('model_version');
            $table->json('payload');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
            $table->unique(['project_id', 'request_id']);
            $table->unique(['project_id', 'request_hash']);
            $table->index(['organization_id', 'project_id']);
            $table->index(['project_id', 'announcement_id']);
            $table->index(['project_id', 'package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_requests');
    }
};
