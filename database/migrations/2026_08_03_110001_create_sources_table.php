<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->string('slug');
            $table->string('name');
            $table->string('source_type');
            $table->string('base_url');
            $table->text('feed_url');
            $table->string('feed_url_hash', 64);
            $table->json('allowed_domains');
            $table->string('parser_profile')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('manual_only')->default(false);
            $table->timestampTz('last_acquired_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->string('last_check_status')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['project_id', 'slug']);
            $table->unique(['project_id', 'feed_url_hash']);
            $table->index(['organization_id', 'project_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
