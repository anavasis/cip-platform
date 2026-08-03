<?php

namespace Tests\Feature\Modules\Editorial\Jobs;

use App\Application\Services\EventBusService;
use App\Application\Services\FeatureFlagService;
use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationResult\EditorialErrorCodes;
use App\Modules\Editorial\Domain\GenerationResult\EditorialGenerationException;
use App\Modules\Editorial\Infrastructure\Jobs\GenerateArticlePreviewJob;
use App\Modules\Editorial\Infrastructure\Persistence\Models\ArticlePreviewModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class JobRetryClassificationTest extends TestCase
{
    public function test_permanent_codes_do_not_retry(): void
    {
        $job = new GenerateArticlePreviewJob('job-id');
        $method = new ReflectionMethod($job, 'isRetryable');
        $method->setAccessible(true);

        foreach ([
            EditorialErrorCodes::BLUEPRINT_INVALID,
            EditorialErrorCodes::PROMPT_CONTEXT_INVALID,
            EditorialErrorCodes::PROMPT_PACKAGE_INVALID,
            EditorialErrorCodes::GENERATION_REQUEST_INVALID,
            EditorialErrorCodes::PROVIDER_CONTENT_TEXT_REQUIRED,
            EditorialErrorCodes::PROVIDER_ERROR,
            EditorialErrorCodes::PROVIDER_EXCEPTION,
            EditorialErrorCodes::PROVIDER_PAYLOAD_INVALID,
            EditorialErrorCodes::GENERATION_RESULT_INVALID,
            EditorialErrorCodes::PREVIEW_BUILD_FAILED,
            EditorialErrorCodes::CAPABILITY_DISABLED,
            EditorialErrorCodes::EDITORIAL_JOB_FAILED,
        ] as $code) {
            $this->assertFalse($method->invoke($job, $code), $code.' should be permanent');
        }
    }

    public function test_lock_and_transient_persistence_retry(): void
    {
        $job = new GenerateArticlePreviewJob('job-id');
        $method = new ReflectionMethod($job, 'isRetryable');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($job, EditorialErrorCodes::ANNOUNCEMENT_LOCKED));
        $this->assertTrue($method->invoke($job, EditorialErrorCodes::TRANSIENT_PERSISTENCE_FAILURE));
    }

    public function test_colon_validation_message_maps_to_permanent_code(): void
    {
        $job = new GenerateArticlePreviewJob('job-id');
        $method = new ReflectionMethod($job, 'exceptionErrorCode');
        $method->setAccessible(true);
        $this->assertSame(
            EditorialErrorCodes::BLUEPRINT_INVALID,
            $method->invoke($job, new \InvalidArgumentException('blueprint_invalid:source_content_hash_required'))
        );
        $this->assertSame(
            EditorialErrorCodes::PROVIDER_CONTENT_TEXT_REQUIRED,
            $method->invoke($job, EditorialGenerationException::permanent(
                EditorialErrorCodes::PROVIDER_CONTENT_TEXT_REQUIRED
            ))
        );
    }

    public function test_provider_logical_failure_terminalizes_with_error_result_and_no_preview(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $this->app->bind(AiProviderInterface::class, fn () => new class implements AiProviderInterface
        {
            public function generate(GenerationRequest $request): array
            {
                return [
                    'ok' => false,
                    'provider_code' => 'stub.fail',
                    'execution_id' => 'exec_fail',
                    'duration_ms' => 1,
                    'error_code' => EditorialErrorCodes::PROVIDER_ERROR,
                    'error_message' => 'nope',
                ];
            }
        });
        $this->app->forgetInstance(GenerateArticlePreviewService::class);
        $this->app->forgetInstance(\App\Modules\Editorial\Application\GenerationOrchestrator::class);

        $platformJob = app(JobEngineService::class)->create(
            'editorial.generate_article_preview',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'announcement_id' => $ann->id,
                'correlation_id' => 'corr-fail',
            ]
        );

        $job = new class($platformJob->id) extends GenerateArticlePreviewJob
        {
            public function attempts(): int
            {
                return 3;
            }
        };

        try {
            $job->handle(
                app(JobEngineService::class),
                app(GenerateArticlePreviewService::class),
                app(CapabilityGate::class),
                app(EventBusService::class),
            );
            $this->fail('expected permanent failure');
        } catch (\Throwable $e) {
            $this->assertSame(EditorialErrorCodes::PROVIDER_ERROR, $e->getMessage());
        }

        $platformJob->refresh();
        $this->assertSame(PlatformJobStatus::Failed, $platformJob->status);
        $this->assertNotSame(PlatformJobStatus::Running, $platformJob->status);
        $this->assertSame(1, GenerationResultModel::query()->where('project_id', $project->id)->where('status', 'error')->count());
        $this->assertSame(0, ArticlePreviewModel::query()->where('project_id', $project->id)->count());
    }

    public function test_missing_content_text_persists_error_and_does_not_retry(): void
    {
        [$organization, $project, $ann] = $this->seedEditorial();
        $this->app->bind(AiProviderInterface::class, fn () => new class implements AiProviderInterface
        {
            public function generate(GenerationRequest $request): array
            {
                return [
                    'ok' => true,
                    'provider_code' => 'stub.bad',
                    'execution_id' => 'exec_bad',
                    'duration_ms' => 1,
                    'artifact_id' => 'art',
                    'artifact_kind' => 'content_candidate',
                    'content_hash' => str_repeat('9', 64),
                    'mime_type' => 'text/plain',
                ];
            }
        });
        $this->app->forgetInstance(GenerateArticlePreviewService::class);
        $this->app->forgetInstance(\App\Modules\Editorial\Application\GenerationOrchestrator::class);

        $out = app(GenerateArticlePreviewService::class)->generate(
            $organization->id,
            $project->id,
            $ann->id,
        );
        $this->assertFalse($out['ok']);
        $this->assertSame(EditorialErrorCodes::PROVIDER_CONTENT_TEXT_REQUIRED, $out['error_code']);
        $this->assertNotEmpty($out['result_id']);
        $this->assertDatabaseHas('generation_results', [
            'result_id' => $out['result_id'],
            'status' => 'error',
            'error_code' => EditorialErrorCodes::PROVIDER_CONTENT_TEXT_REQUIRED,
        ]);
        $this->assertSame(0, ArticlePreviewModel::query()->where('project_id', $project->id)->count());
    }

    /**
     * @return array{0: mixed, 1: Project, 2: Announcement, 3?: mixed}
     */
    private function seedEditorial(): array
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Retry Project',
            'slug' => 'retry-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $source = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'slug' => 'retry-src-'.uniqid(),
            'name' => 'retry',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/r-'.uniqid().'.xml',
            'feed_url_hash' => hash('sha256', uniqid('f', true)),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ]);
        $ann = Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
            'identity_hash' => hash('sha256', uniqid('id', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/r-'.uniqid(),
            'raw_title' => 'Retry Title',
            'content_hash' => hash('sha256', uniqid('c', true)),
            'raw_payload' => ['title' => 'Retry Title', 'summary' => 'summary'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $flags = app(FeatureFlagService::class);
        foreach ([CapabilityGate::EDITORIAL, CapabilityGate::EDITORIAL_GENERATION] as $key) {
            $flags->upsert($key, true, FeatureFlagScope::Project, null, $organization->id, $project->id);
        }

        return [$organization, $project, $ann, $owner];
    }
}
