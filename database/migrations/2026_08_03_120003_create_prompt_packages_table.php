<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('announcement_id');
            $table->string('package_id');
            $table->string('context_id');
            $table->string('context_hash', 64);
            $table->string('blueprint_id');
            $table->string('package_hash', 64);
            $table->string('status');
            $table->string('template_id');
            $table->string('template_version');
            $table->timestampTz('sealed_at_utc')->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
            $table->unique(['project_id', 'package_id']);
            $table->unique(['project_id', 'package_hash']);
            $table->index(['organization_id', 'project_id']);
            $table->index(['project_id', 'announcement_id']);
            $table->index(['project_id', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_packages');
    }
};
