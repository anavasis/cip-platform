<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blueprints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('announcement_id');
            $table->string('blueprint_id');
            $table->string('lineage_id')->nullable();
            $table->unsignedInteger('blueprint_revision')->default(1);
            $table->string('status');
            $table->string('article_type');
            $table->string('source_content_hash', 64)->nullable();
            $table->unsignedInteger('announcement_revision_no')->default(1);
            $table->json('payload');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
            $table->unique(['project_id', 'blueprint_id']);
            $table->index(['organization_id', 'project_id']);
            $table->index(['project_id', 'announcement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blueprints');
    }
};
