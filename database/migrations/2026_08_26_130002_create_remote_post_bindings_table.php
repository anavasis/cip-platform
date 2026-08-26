<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_post_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('content_entity_id');
            $table->string('remote_system')->default('wordpress');
            $table->string('remote_post_id')->nullable();
            $table->string('canonical_url');
            $table->string('slug')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->uuid('confirmed_by')->nullable();
            $table->timestampTz('bound_at');
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('content_entity_id')->references('id')->on('content_entities')->cascadeOnDelete();
            $table->unique(['organization_id', 'project_id', 'content_entity_id', 'remote_system']);
            $table->unique(['organization_id', 'project_id', 'remote_system', 'remote_post_id']);
            $table->index(['organization_id', 'project_id']);
            $table->index(['content_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_post_bindings');
    }
};
