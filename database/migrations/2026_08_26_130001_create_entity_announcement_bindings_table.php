<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_announcement_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('content_entity_id');
            $table->uuid('announcement_id');
            $table->string('binding_role')->default('supplemental');
            $table->unsignedInteger('source_revision_at_bind');
            $table->string('content_hash_at_bind', 64);
            $table->timestampTz('bound_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('content_entity_id')->references('id')->on('content_entities')->cascadeOnDelete();
            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
            $table->unique(['organization_id', 'project_id', 'content_entity_id', 'announcement_id']);
            $table->index(['organization_id', 'project_id']);
            $table->index(['content_entity_id']);
            $table->index(['announcement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_announcement_bindings');
    }
};
