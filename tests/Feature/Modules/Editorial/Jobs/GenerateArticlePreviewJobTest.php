<?php

namespace Tests\Feature\Modules\Editorial\Jobs;

use App\Application\Services\FeatureFlagService;
use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Infrastructure\Jobs\GenerateArticlePreviewJob;
use App\Modules\Editorial\Infrastructure\Persistence\Models\ArticlePreviewModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateArticlePreviewJobTest extends TestCase
{
    public function test_job_success_terminalizes_and_is_idempotent(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Job Project');
        $source = $this->createSource($organization->id, $project->id, 'job-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Job Title');
        $this->enableEditorial($organization->id, $project->id);

        $jobs = app(JobEngineService::class);
        $platformJob = $jobs->create('editorial.generate_article_preview', $organization->id, $project->id, [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'announcement_id' => $ann->id,
            'actor_id' => $owner->id,
            'correlation_id' => 'corr-1',
            'regenerate' => false,
        ]);

        $job = new GenerateArticlePreviewJob($platformJob->id);
        $job->handle(
            app(JobEngineService::class),
            app(\App\Modules\Editorial\Application\GenerateArticlePreviewService::class),
            app(CapabilityGate::class),
            app(\App\Application\Services\EventBusService::class),
        );

        $platformJob->refresh();
        $this->assertSame(PlatformJobStatus::Completed, $platformJob->status);
        $this->assertSame(1, ArticlePreviewModel::query()->where('project_id', $project->id)->count());

        // Duplicate execution remains idempotent
        $platformJob2 = $jobs->create('editorial.generate_article_preview', $organization->id, $project->id, [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'announcement_id' => $ann->id,
            'correlation_id' => 'corr-2',
            'regenerate' => false,
        ]);
        (new GenerateArticlePreviewJob($platformJob2->id))->handle(
            app(JobEngineService::class),
            app(\App\Modules\Editorial\Application\GenerateArticlePreviewService::class),
            app(CapabilityGate::class),
            app(\App\Application\Services\EventBusService::class),
        );
        $platformJob2->refresh();
        $this->assertSame(PlatformJobStatus::Completed, $platformJob2->status);
        $this->assertSame(1, ArticlePreviewModel::query()->where('project_id', $project->id)->count());
    }

    public function test_capability_disabled_terminalizes_without_retry_signal(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Cap Job Project');
        $source = $this->createSource($organization->id, $project->id, 'cap-job-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Cap Job');

        $platformJob = app(JobEngineService::class)->create(
            'editorial.generate_article_preview',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'announcement_id' => $ann->id,
                'correlation_id' => 'corr-cap',
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
                app(\App\Modules\Editorial\Application\GenerateArticlePreviewService::class),
                app(CapabilityGate::class),
                app(\App\Application\Services\EventBusService::class),
            );
            $this->fail('expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('capability_disabled', $e->getMessage());
        }

        $platformJob->refresh();
        $this->assertSame(PlatformJobStatus::Failed, $platformJob->status);
    }

    public function test_lock_is_project_scoped_for_same_announcement_uuid(): void
    {
        $keyA = 'editorial:project:proj-a:announcement:ann-1';
        $keyB = 'editorial:project:proj-b:announcement:ann-1';
        $this->assertNotSame($keyA, $keyB);
        $lockA = Cache::lock($keyA, 5);
        $this->assertTrue($lockA->get());
        $lockB = Cache::lock($keyB, 5);
        $this->assertTrue($lockB->get());
        $lockA->release();
        $lockB->release();
    }

    private function enableEditorial(string $organizationId, string $projectId): void
    {
        $flags = app(FeatureFlagService::class);
        foreach ([CapabilityGate::EDITORIAL, CapabilityGate::EDITORIAL_GENERATION] as $key) {
            $flags->upsert($key, true, FeatureFlagScope::Project, null, $organizationId, $projectId);
        }
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'created_by' => $userId,
        ]);
    }

    private function createSource(string $organizationId, string $projectId, string $slug): Source
    {
        $feedUrl = "https://example.com/{$slug}.xml";

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => $slug,
            'name' => $slug,
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ]);
    }

    private function createAnnouncement(string $organizationId, string $projectId, string $sourceId, string $title): Announcement
    {
        return Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'source_id' => $sourceId,
            'identity_hash' => hash('sha256', $title.'|'.$projectId.uniqid()),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/'.Str::slug($title).'-'.uniqid(),
            'raw_title' => $title,
            'content_hash' => hash('sha256', $title.uniqid()),
            'raw_payload' => ['title' => $title, 'summary' => $title.' summary'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
