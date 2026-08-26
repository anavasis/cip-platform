<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_entities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->string('entity_id');
            $table->string('entity_type')->default('process');
            $table->string('label');
            $table->string('code')->nullable();
            $table->string('organization_body')->nullable();
            $table->string('source_family')->default('other');
            $table->json('thematic_categories')->default('[]');
            $table->string('content_role')->default('satellite');
            $table->string('lifecycle_status')->default('verification_required');
            $table->timestampTz('application_open_at')->nullable();
            $table->timestampTz('application_deadline_at')->nullable();
            $table->unsignedInteger('positions_count')->nullable();
            $table->text('next_step_label')->nullable();
            $table->string('verification_status')->default('verification_required');
            $table->timestampTz('last_verified_at')->nullable();
            $table->timestampTz('last_changed_at')->nullable();
            $table->string('hub_display_section')->nullable();
            $table->boolean('hub_member')->default(false);
            $table->string('archive_state')->default('active');
            $table->boolean('publish_eligible')->default(false);
            $table->uuid('verified_announcement_id')->nullable();
            $table->string('verified_content_hash')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['organization_id', 'project_id', 'entity_id']);
            $table->index(['organization_id', 'project_id']);
            $table->index(['project_id', 'hub_member']);
            $table->index(['project_id', 'publish_eligible']);
            $table->index(['project_id', 'lifecycle_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_entities');
    }
};
