<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquisition_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->string('run_id')->unique();
            $table->string('status');
            $table->string('error_code')->nullable();
            $table->unsignedInteger('sources_requested')->default(0);
            $table->unsignedInteger('sources_succeeded')->default(0);
            $table->unsignedInteger('sources_failed')->default(0);
            $table->float('duration_ms')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('acquisition_run_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('acquisition_run_id');
            $table->uuid('organization_id');
            $table->uuid('project_id');
            $table->uuid('source_id')->nullable();
            $table->boolean('success');
            $table->string('error_code')->nullable();
            $table->json('result_meta')->nullable();
            $table->timestamps();

            $table->foreign('acquisition_run_id')
                ->references('id')
                ->on('acquisition_runs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisition_run_items');
        Schema::dropIfExists('acquisition_runs');
    }
};
