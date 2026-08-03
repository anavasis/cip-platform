<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_previews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('announcement_id');
            $table->string('preview_id');
            $table->string('preview_key');
            $table->string('request_id');
            $table->string('result_id');
            $table->string('result_hash', 64);
            $table->text('title');
            $table->longText('body');
            $table->timestampTz('created_at_utc');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
            $table->unique(['project_id', 'preview_id']);
            $table->unique(['project_id', 'preview_key']);
            $table->index(['organization_id', 'project_id']);
            $table->index(['project_id', 'announcement_id']);
            $table->index(['project_id', 'result_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_previews');
    }
};
