<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('source_id');
            $table->string('identity_hash', 64);
            $table->string('identity_basis');
            $table->string('source_guid')->nullable();
            $table->text('canonical_url');
            $table->timestampTz('source_published_at')->nullable();
            $table->text('raw_title');
            $table->string('content_hash', 64);
            $table->json('raw_payload');
            $table->unsignedInteger('revision_no')->default(1);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('source_id')->references('id')->on('sources')->cascadeOnDelete();
            $table->unique(['project_id', 'source_id', 'identity_hash']);
            $table->index(['organization_id', 'project_id']);
            $table->index(['project_id', 'revision_no']);
            $table->index(['project_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
