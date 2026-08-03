<?php

namespace Tests\Feature\Modules\Editorial\GenerationResult;

use App\Application\Services\FeatureFlagService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprint;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintRepositoryInterface;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationResult\EditorialErrorCodes;
use App\Modules\Editorial\Domain\GenerationResult\EditorialGenerationException;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResult;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultStatus;
use App\Modules\Editorial\Infrastructure\Persistence\Models\ArticlePreviewModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use Illuminate\Support\Str;
use Tests\TestCase;

class DurableErrorPersistenceGateTest extends TestCase
{
    public function test_error_result_save_false_rolls_back_without_event_or_handled_marker(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $this->bindFailingProvider();

        $inner = app(GenerationResultRepositoryInterface::class);
        $this->app->bind(GenerationResultRepositoryInterface::class, function () use ($inner) {
            return new class($inner) implements GenerationResultRepositoryInterface
            {
                public function __construct(private readonly GenerationResultRepositoryInterface $inner) {}

                public function save(string $organizationId, string $projectId, GenerationResult $result): bool
                {
                    return false;
                }

                public function findById(string $organizationId, string $projectId, string $resultId): ?GenerationResult
                {
                    return $this->inner->findById($organizationId, $projectId, $resultId);
                }

                public function findByResultHash(string $organizationId, string $projectId, string $resultHash): ?GenerationResult
                {
                    return $this->inner->findByResultHash($organizationId, $projectId, $resultHash);
                }

                public function findByRequestId(string $organizationId, string $projectId, string $requestId): ?GenerationResult
                {
                    return $this->inner->findByRequestId($organizationId, $projectId, $requestId);
                }

                public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?GenerationResult
                {
                    return $this->inner->findLatestForAnnouncement($organizationId, $projectId, $announcementId);
                }
            };
        });
        $this->forgetEditorialServices();

        $beforeFailed = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();

        try {
            app(GenerateArticlePreviewService::class)->generate(
                $organization->id,
                $project->id,
                $ann->id,
                $owner->id,
            );
            $this->fail('Expected transient persistence failure');
        } catch (EditorialGenerationException $e) {
            $this->assertSame(EditorialErrorCodes::TRANSIENT_PERSISTENCE_FAILURE, $e->errorCode());
            $this->assertTrue(EditorialErrorCodes::isRetryable($e->errorCode()));
        }

        $this->assertSame(0, GenerationResultModel::query()->where('project_id', $project->id)->count());
        $this->assertSame(0, ArticlePreviewModel::query()->where('project_id', $project->id)->count());
        $this->assertSame(
            $beforeFailed,
            StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count(),
        );
    }

    public function test_error_result_save_exception_rolls_back_without_event(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $this->bindFailingProvider();

        $inner = app(GenerationResultRepositoryInterface::class);
        $this->app->bind(GenerationResultRepositoryInterface::class, function () use ($inner) {
            return new class($inner) implements GenerationResultRepositoryInterface
            {
                public function __construct(private readonly GenerationResultRepositoryInterface $inner) {}

                public function save(string $organizationId, string $projectId, GenerationResult $result): bool
                {
                    throw new \RuntimeException('forced_result_save_exception');
                }

                public function findById(string $organizationId, string $projectId, string $resultId): ?GenerationResult
                {
                    return $this->inner->findById($organizationId, $projectId, $resultId);
                }

                public function findByResultHash(string $organizationId, string $projectId, string $resultHash): ?GenerationResult
                {
                    return $this->inner->findByResultHash($organizationId, $projectId, $resultHash);
                }

                public function findByRequestId(string $organizationId, string $projectId, string $requestId): ?GenerationResult
                {
                    return $this->inner->findByRequestId($organizationId, $projectId, $requestId);
                }

                public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?GenerationResult
                {
                    return $this->inner->findLatestForAnnouncement($organizationId, $projectId, $announcementId);
                }
            };
        });
        $this->forgetEditorialServices();

        $beforeFailed = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();

        try {
            app(GenerateArticlePreviewService::class)->generate(
                $organization->id,
                $project->id,
                $ann->id,
                $owner->id,
            );
            $this->fail('Expected result save exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced_result_save_exception', $e->getMessage());
        }

        $this->assertSame(0, GenerationResultModel::query()->where('project_id', $project->id)->count());
        $this->assertSame(
            $beforeFailed,
            StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count(),
        );
    }

    public function test_supporting_lineage_save_failure_emits_no_event_and_no_error_row(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $this->bindFailingProvider();

        $inner = app(ContentBlueprintRepositoryInterface::class);
        $this->app->bind(ContentBlueprintRepositoryInterface::class, function () use ($inner) {
            return new class($inner) implements ContentBlueprintRepositoryInterface
            {
                public function __construct(private readonly ContentBlueprintRepositoryInterface $inner) {}

                public function save(string $organizationId, string $projectId, ContentBlueprint $blueprint): bool
                {
                    return false;
                }

                public function findById(string $organizationId, string $projectId, string $blueprintId): ?ContentBlueprint
                {
                    return $this->inner->findById($organizationId, $projectId, $blueprintId);
                }

                public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?ContentBlueprint
                {
                    return $this->inner->findLatestForAnnouncement($organizationId, $projectId, $announcementId);
                }
            };
        });
        $this->forgetEditorialServices();

        $beforeFailed = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();

        try {
            app(GenerateArticlePreviewService::class)->generate(
                $organization->id,
                $project->id,
                $ann->id,
                $owner->id,
            );
            $this->fail('Expected transient persistence failure from lineage save');
        } catch (EditorialGenerationException $e) {
            $this->assertSame(EditorialErrorCodes::TRANSIENT_PERSISTENCE_FAILURE, $e->errorCode());
        }

        $this->assertSame(0, GenerationResultModel::query()->where('project_id', $project->id)->count());
        $this->assertSame(
            $beforeFailed,
            StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count(),
        );
    }

    public function test_successful_error_persistence_emits_generation_failed_once_without_preview(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $this->bindFailingProvider();

        $beforeFailed = StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count();

        $out = app(GenerateArticlePreviewService::class)->generate(
            $organization->id,
            $project->id,
            $ann->id,
            $owner->id,
        );

        $this->assertFalse($out['ok']);
        $this->assertTrue($out['failure_event_emitted'] ?? false);
        $this->assertSame(EditorialErrorCodes::PROVIDER_ERROR, $out['error_code']);
        $this->assertDatabaseHas('generation_results', [
            'project_id' => $project->id,
            'result_id' => $out['result_id'],
            'status' => GenerationResultStatus::ERROR,
            'error_code' => EditorialErrorCodes::PROVIDER_ERROR,
        ]);
        $this->assertSame(0, ArticlePreviewModel::query()->where('project_id', $project->id)->count());
        $this->assertSame(
            1,
            StoredEvent::query()->where('event_type', 'editorial.generation_failed')->count() - $beforeFailed,
        );
    }

    public function test_after_commit_callback_not_executed_when_error_save_rolls_back(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $this->bindFailingProvider();

        $beforeIds = StoredEvent::query()->pluck('id')->all();

        $inner = app(GenerationResultRepositoryInterface::class);
        $this->app->bind(GenerationResultRepositoryInterface::class, function () use ($inner) {
            return new class($inner) implements GenerationResultRepositoryInterface
            {
                public function __construct(private readonly GenerationResultRepositoryInterface $inner) {}

                public function save(string $organizationId, string $projectId, GenerationResult $result): bool
                {
                    return false;
                }

                public function findById(string $organizationId, string $projectId, string $resultId): ?GenerationResult
                {
                    return $this->inner->findById($organizationId, $projectId, $resultId);
                }

                public function findByResultHash(string $organizationId, string $projectId, string $resultHash): ?GenerationResult
                {
                    return $this->inner->findByResultHash($organizationId, $projectId, $resultHash);
                }

                public function findByRequestId(string $organizationId, string $projectId, string $requestId): ?GenerationResult
                {
                    return $this->inner->findByRequestId($organizationId, $projectId, $requestId);
                }

                public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?GenerationResult
                {
                    return $this->inner->findLatestForAnnouncement($organizationId, $projectId, $announcementId);
                }
            };
        });
        $this->forgetEditorialServices();

        try {
            app(GenerateArticlePreviewService::class)->generate(
                $organization->id,
                $project->id,
                $ann->id,
                $owner->id,
            );
        } catch (\Throwable) {
        }

        $new = StoredEvent::query()->whereNotIn('id', $beforeIds)->get();
        foreach ($new as $event) {
            $this->assertStringNotContainsString('generation_failed', (string) $event->event_type);
        }
    }

    private function bindFailingProvider(): void
    {
        $this->app->bind(AiProviderInterface::class, function () {
            return new class implements AiProviderInterface
            {
                public function generate(GenerationRequest $request): array
                {
                    return [
                        'ok' => false,
                        'provider_code' => 'stub.fail',
                        'execution_id' => 'exec_err',
                        'duration_ms' => 1,
                        'error_code' => EditorialErrorCodes::PROVIDER_ERROR,
                        'error_message' => 'provider rejected',
                    ];
                }
            };
        });
        $this->forgetEditorialServices();
    }

    private function forgetEditorialServices(): void
    {
        $this->app->forgetInstance(GenerateArticlePreviewService::class);
        $this->app->forgetInstance(\App\Modules\Editorial\Application\GenerationOrchestrator::class);
    }

    /**
     * @return array{0: mixed, 1: Project, 2: Announcement, 3: mixed}
     */
    private function seedEditorial(): array
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Durable Err',
            'slug' => 'durable-err-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $source = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'slug' => 'durable-err-'.uniqid(),
            'name' => 'durable',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/durable-'.uniqid().'.xml',
            'feed_url_hash' => hash('sha256', uniqid('feed', true)),
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
            'canonical_url' => 'https://example.com/a-'.uniqid(),
            'raw_title' => 'Durable Error Title',
            'content_hash' => hash('sha256', uniqid('c', true)),
            'raw_payload' => ['title' => 'Durable Error Title', 'summary' => 'summary'],
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
