<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->uuid('resource_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index('action');
        });

        Schema::create('stored_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['event_type', 'occurred_at']);
        });

        Schema::create('platform_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->string('job_type');
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('schedule_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id')->nullable();
            $table->string('name');
            $table->string('cron_expression');
            $table->string('job_type');
            $table->json('payload')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        Schema::create('monitoring_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->decimal('value', 20, 6);
            $table->json('tags')->nullable();
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['name', 'recorded_at']);
        });

        Schema::create('connector_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('project_connectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('connector_type_id');
            $table->string('name');
            $table->json('config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('connector_type_id')->references('id')->on('connector_types')->cascadeOnDelete();
            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_connectors');
        Schema::dropIfExists('connector_types');
        Schema::dropIfExists('monitoring_metrics');
        Schema::dropIfExists('schedule_definitions');
        Schema::dropIfExists('platform_jobs');
        Schema::dropIfExists('stored_events');
        Schema::dropIfExists('audit_events');
    }
};
