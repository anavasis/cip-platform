<?php

namespace Tests\Feature\Modules\Editorial\Persistence;

use App\Application\Services\FeatureFlagService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\Article\ArticlePreview;
use App\Modules\Editorial\Domain\Article\ArticlePreviewRepositoryInterface;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResult;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultStatus;
use App\Modules\Editorial\Infrastructure\Persistence\Models\ArticlePreviewModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use Illuminate\Support\Str;
use Tests\TestCase;

class AtomicResultPreviewPersistenceTest extends TestCase
{
    public function test_successful_result_and_preview_commit_together(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $result = app(GenerateArticlePreviewService::class)->generate(
            $organization->id,
            $project->id,
            $ann->id,
            $owner->id,
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['stages']['preview_stored'] ?? false);
        $this->assertDatabaseHas('generation_results', [
            'project_id' => $project->id,
            'result_id' => $result['result_id'],
            'status' => GenerationResultStatus::SUCCESS,
        ]);
        $this->assertDatabaseHas('article_previews', [
            'project_id' => $project->id,
            'preview_id' => $result['preview_id'],
            'result_id' => $result['result_id'],
        ]);
    }

    public function test_forced_preview_save_failure_rolls_back_success_result(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();

        $this->app->bind(ArticlePreviewRepositoryInterface::class, function () {
            return new class implements ArticlePreviewRepositoryInterface
            {
                public function save(ArticlePreview $preview): bool
                {
                    return false;
                }

                public function findById(string $organizationId, string $projectId, string $previewId): ?ArticlePreview
                {
                    return null;
                }

                public function findLatestForAnnouncement(
                    string $organizationId,
                    string $projectId,
                    string $announcementId
                ): ?ArticlePreview {
                    return null;
                }

                public function findByPreviewKey(string $organizationId, string $projectId, string $previewKey): ?ArticlePreview
                {
                    return null;
                }
            };
        });
        $this->app->forgetInstance(GenerateArticlePreviewService::class);

        try {
            app(GenerateArticlePreviewService::class)->generate(
                $organization->id,
                $project->id,
                $ann->id,
                $owner->id,
            );
            $this->fail('Expected preview_save_failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('preview_save_failed', $e->getMessage());
        }

        $this->assertSame(0, GenerationResultModel::query()->where('project_id', $project->id)->count());
        $this->assertSame(0, ArticlePreviewModel::query()->where('project_id', $project->id)->count());
    }

    public function test_forced_result_save_failure_leaves_no_preview(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();

        $this->app->bind(GenerationResultRepositoryInterface::class, function () {
            return new class implements GenerationResultRepositoryInterface
            {
                public function save(string $organizationId, string $projectId, GenerationResult $result): bool
                {
                    return false;
                }

                public function findById(string $organizationId, string $projectId, string $resultId): ?GenerationResult
                {
                    return null;
                }

                public function findByResultHash(string $organizationId, string $projectId, string $resultHash): ?GenerationResult
                {
                    return null;
                }

                public function findByRequestId(string $organizationId, string $projectId, string $requestId): ?GenerationResult
                {
                    return null;
                }

                public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?GenerationResult
                {
                    return null;
                }
            };
        });
        $this->app->forgetInstance(GenerateArticlePreviewService::class);

        try {
            app(GenerateArticlePreviewService::class)->generate(
                $organization->id,
                $project->id,
                $ann->id,
                $owner->id,
            );
            $this->fail('Expected generation_result_persist_failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('generation_result_persist_failed', $e->getMessage());
        }

        $this->assertSame(0, GenerationResultModel::query()->where('project_id', $project->id)->count());
        $this->assertSame(0, ArticlePreviewModel::query()->where('project_id', $project->id)->count());
    }

    public function test_error_result_commits_without_preview(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();

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
                        'error_code' => 'provider_error',
                        'error_message' => 'provider rejected',
                    ];
                }
            };
        });

        $this->app->forgetInstance(GenerateArticlePreviewService::class);
        $this->app->forgetInstance(\App\Modules\Editorial\Application\GenerationOrchestrator::class);

        $out = app(GenerateArticlePreviewService::class)->generate(
            $organization->id,
            $project->id,
            $ann->id,
            $owner->id,
        );

        $this->assertFalse($out['ok']);
        $this->assertNotEmpty($out['result_id']);
        $this->assertDatabaseHas('generation_results', [
            'project_id' => $project->id,
            'result_id' => $out['result_id'],
            'status' => GenerationResultStatus::ERROR,
        ]);
        $this->assertSame(0, ArticlePreviewModel::query()->where('project_id', $project->id)->count());
    }

    public function test_success_commit_emits_persistence_events_once_after_commit(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $beforeIds = StoredEvent::query()->pluck('id')->all();

        $result = app(GenerateArticlePreviewService::class)->generate(
            $organization->id,
            $project->id,
            $ann->id,
            $owner->id,
        );
        $this->assertTrue($result['ok']);

        $new = StoredEvent::query()->whereNotIn('id', $beforeIds)->get();
        $previewCreated = $new->filter(fn ($e) => $e->event_type === 'editorial.article_preview_created');
        $completed = $new->filter(fn ($e) => $e->event_type === 'editorial.generation_completed');
        $this->assertCount(1, $previewCreated);
        $this->assertCount(1, $completed);

        foreach ($new as $event) {
            $encoded = json_encode($event->payload);
            $this->assertStringNotContainsString('Stub article preview', (string) $encoded);
            $this->assertArrayNotHasKey('body', is_array($event->payload) ? $event->payload : []);
        }
    }

    public function test_transaction_rollback_emits_no_persistence_event(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $beforeIds = StoredEvent::query()->pluck('id')->all();

        $this->app->bind(ArticlePreviewRepositoryInterface::class, function () {
            return new class implements ArticlePreviewRepositoryInterface
            {
                public function save(ArticlePreview $preview): bool
                {
                    return false;
                }

                public function findById(string $organizationId, string $projectId, string $previewId): ?ArticlePreview
                {
                    return null;
                }

                public function findLatestForAnnouncement(
                    string $organizationId,
                    string $projectId,
                    string $announcementId
                ): ?ArticlePreview {
                    return null;
                }

                public function findByPreviewKey(string $organizationId, string $projectId, string $previewKey): ?ArticlePreview
                {
                    return null;
                }
            };
        });
        $this->app->forgetInstance(GenerateArticlePreviewService::class);

        try {
            app(GenerateArticlePreviewService::class)->generate(
                $organization->id,
                $project->id,
                $ann->id,
                $owner->id,
            );
        } catch (\RuntimeException) {
        }

        $new = StoredEvent::query()->whereNotIn('id', $beforeIds)->get();
        foreach ($new as $event) {
            $type = (string) $event->event_type;
            $this->assertStringNotContainsString('article_preview_created', $type);
            $this->assertStringNotContainsString('generation_completed', $type);
            $this->assertStringNotContainsString('blueprint_created', $type);
        }
    }

    public function test_duplicate_preview_save_does_not_duplicate_rows(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedEditorial();
        $first = app(GenerateArticlePreviewService::class)->generate(
            $organization->id,
            $project->id,
            $ann->id,
            $owner->id,
        );
        $this->assertTrue($first['ok']);

        $preview = app(ArticlePreviewRepositoryInterface::class)->findById(
            $organization->id,
            $project->id,
            $first['preview_id'],
        );
        $this->assertNotNull($preview);
        $this->assertTrue(app(ArticlePreviewRepositoryInterface::class)->save($preview));
        $this->assertSame(1, ArticlePreviewModel::query()->where('preview_id', $first['preview_id'])->count());
    }

    /**
     * @return array{0: mixed, 1: Project, 2: Announcement, 3: mixed}
     */
    private function seedEditorial(): array
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Atomic Svc',
            'slug' => 'atomic-svc-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $source = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'slug' => 'atomic-svc-'.uniqid(),
            'name' => 'atomic',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/atomic-'.uniqid().'.xml',
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
            'raw_title' => 'Atomic Title',
            'content_hash' => hash('sha256', uniqid('c', true)),
            'raw_payload' => ['title' => 'Atomic Title', 'summary' => 'summary'],
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
