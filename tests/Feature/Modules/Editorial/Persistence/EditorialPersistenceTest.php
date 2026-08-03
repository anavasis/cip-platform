<?php

namespace Tests\Feature\Modules\Editorial\Persistence;

use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Domain\Article\ArticlePreview;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationModelReference;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationParameters;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestBuilder;
use App\Modules\Editorial\Domain\GenerationResult\GeneratedArtifactReference;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultBuilder;
use App\Modules\Editorial\Domain\GenerationResult\ProviderExecutionReference;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptPackage\BlueprintReference;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptTemplateReference;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentArticlePreviewRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentContentBlueprintRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentGenerationRequestRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentGenerationResultRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentPromptContextRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentPromptPackageRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditorialPersistenceTest extends TestCase
{
    public function test_all_six_editorial_tables_exist(): void
    {
        foreach ([
            'content_blueprints',
            'prompt_contexts',
            'prompt_packages',
            'generation_requests',
            'generation_results',
            'article_previews',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' missing');
        }
    }

    public function test_round_trip_and_tenant_isolation_and_hash_uniqueness(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $projectA = $this->createProject($organization->id, $owner->id, 'Ed Project A');
        $projectB = $this->createProject($organization->id, $owner->id, 'Ed Project B');
        $sourceA = $this->createSource($organization->id, $projectA->id, 'ed-source-a');
        $sourceB = $this->createSource($organization->id, $projectB->id, 'ed-source-b');
        $annA = $this->createAnnouncement($organization->id, $projectA->id, $sourceA->id, 'Title A');
        $annB = $this->createAnnouncement($organization->id, $projectB->id, $sourceB->id, 'Title B');

        $snapshot = [
            'announcement_id' => $annA->id,
            'raw_title' => 'Title A',
            'source_content_hash' => str_repeat('a', 64),
            'announcement_revision_no' => 1,
            'language' => 'en',
            'summary' => 'Summary A',
            'source_id' => $sourceA->id,
        ];

        $blueprint = (new ContentBlueprintBuilder)->buildFromAnnouncement($snapshot);
        $context = (new PromptContextBuilder)->buildFromAnnouncementAndBlueprint($snapshot, $blueprint);
        $package = (new PromptPackageBuilder)->buildFromContextAndBlueprint(
            $context,
            new BlueprintReference([
                'blueprint_id' => $blueprint->blueprintId(),
                'blueprint_revision' => $blueprint->blueprintRevision(),
                'announcement_id' => $blueprint->announcementId(),
            ]),
            new PromptTemplateReference([
                'template_id' => 'smce.editorial.slice_a',
                'template_version' => '1.0.0',
            ])
        );
        $request = (new GenerationRequestBuilder)->buildFromPackage(
            $package,
            new GenerationModelReference([
                'model_id' => 'smce.stub.deterministic',
                'model_version' => '1',
            ]),
            new GenerationParameters([
                'temperature' => 0.0,
                'max_output_tokens' => 2048,
                'response_format' => 'text',
                'seed' => 1,
            ])
        );
        $result = (new GenerationResultBuilder)->buildSuccessFromRequest(
            $request,
            new ProviderExecutionReference([
                'provider_code' => 'stub.deterministic',
                'execution_id' => 'exec_1',
                'started_at_utc' => gmdate('Y-m-d H:i:s'),
                'completed_at_utc' => gmdate('Y-m-d H:i:s'),
            ]),
            [new GeneratedArtifactReference([
                'artifact_id' => 'art_1',
                'content_hash' => str_repeat('b', 64),
            ])],
            ['duration_ms' => 1]
        );
        $preview = new ArticlePreview([
            'preview_id' => 'apv_'.substr(hash('sha256', $result->resultId().'|'.$request->requestId()), 0, 24),
            'organization_id' => $organization->id,
            'project_id' => $projectA->id,
            'announcement_id' => $annA->id,
            'request_id' => $request->requestId(),
            'result_id' => $result->resultId(),
            'result_hash' => $result->resultHash(),
            'title' => 'Title A',
            'body' => 'Body A',
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);

        $blueprints = new EloquentContentBlueprintRepository;
        $contexts = new EloquentPromptContextRepository;
        $packages = new EloquentPromptPackageRepository;
        $requests = new EloquentGenerationRequestRepository;
        $results = new EloquentGenerationResultRepository;
        $previews = new EloquentArticlePreviewRepository;

        $this->assertTrue($blueprints->save($organization->id, $projectA->id, $blueprint));
        $this->assertTrue($contexts->save($organization->id, $projectA->id, $context));
        $this->assertTrue($packages->save($organization->id, $projectA->id, $package));
        $this->assertTrue($requests->save($organization->id, $projectA->id, $request));
        $this->assertTrue($results->save($organization->id, $projectA->id, $result));
        $this->assertTrue($previews->save($preview));

        $this->assertSame(
            $blueprint->blueprintId(),
            $blueprints->findById($organization->id, $projectA->id, $blueprint->blueprintId())?->blueprintId()
        );
        $this->assertNull($blueprints->findById($organization->id, $projectB->id, $blueprint->blueprintId()));
        $this->assertNull($contexts->findById($organization->id, $projectB->id, $context->contextId()));
        $this->assertNull($packages->findByPackageHash($organization->id, $projectB->id, $package->packageHash()));
        $this->assertNull($requests->findByRequestHash($organization->id, $projectB->id, $request->requestHash()));
        $this->assertNull($results->findByResultHash($organization->id, $projectB->id, $result->resultHash()));
        $this->assertNull($previews->findLatestForAnnouncement($organization->id, $projectB->id, $annA->id));
        $this->assertNull($previews->findLatestForAnnouncement($organization->id, $projectA->id, $annB->id));

        // Duplicate request_hash recovery (idempotent)
        $this->assertTrue($requests->save($organization->id, $projectA->id, $request));
        $this->assertSame(1, DB::table('generation_requests')->where('project_id', $projectA->id)->count());

        // Same hash allowed in another project (tenant-scoped uniqueness)
        $snapshotB = array_merge($snapshot, [
            'announcement_id' => $annB->id,
            'raw_title' => 'Title B',
            'source_id' => $sourceB->id,
        ]);
        $blueprintB = (new ContentBlueprintBuilder)->buildFromAnnouncement($snapshotB);
        $contextB = (new PromptContextBuilder)->buildFromAnnouncementAndBlueprint($snapshotB, $blueprintB);
        // Force identical context_hash by reusing payload is hard; instead insert package_hash collision via raw for project B
        $this->assertTrue($packages->save($organization->id, $projectB->id, $package)); // same package_hash, different project
        $this->assertSame(2, DB::table('prompt_packages')->where('package_hash', $package->packageHash())->count());

        // Durable preview reload
        $loaded = $previews->findLatestForAnnouncement($organization->id, $projectA->id, $annA->id);
        $this->assertNotNull($loaded);
        $this->assertSame('Body A', $loaded->body());
        $this->assertSame('Title A', $loaded->title());
    }

    public function test_atomic_result_and_preview_persistence(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Atomic Project');
        $source = $this->createSource($organization->id, $project->id, 'atomic-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Atomic');

        $results = new EloquentGenerationResultRepository;
        $previews = new EloquentArticlePreviewRepository;

        $snapshot = [
            'announcement_id' => $ann->id,
            'raw_title' => 'Atomic',
            'source_content_hash' => str_repeat('c', 64),
            'announcement_revision_no' => 1,
            'language' => 'en',
            'summary' => 'Atomic summary',
        ];
        $blueprint = (new ContentBlueprintBuilder)->buildFromAnnouncement($snapshot);
        $context = (new PromptContextBuilder)->buildFromAnnouncementAndBlueprint($snapshot, $blueprint);
        $package = (new PromptPackageBuilder)->buildFromContextAndBlueprint(
            $context,
            new BlueprintReference([
                'blueprint_id' => $blueprint->blueprintId(),
                'blueprint_revision' => $blueprint->blueprintRevision(),
                'announcement_id' => $blueprint->announcementId(),
            ]),
            new PromptTemplateReference([
                'template_id' => 'smce.editorial.slice_a',
                'template_version' => '1.0.0',
            ])
        );
        $request = (new GenerationRequestBuilder)->buildFromPackage(
            $package,
            new GenerationModelReference(['model_id' => 'smce.stub.deterministic', 'model_version' => '1']),
            new GenerationParameters([
                'temperature' => 0.0,
                'max_output_tokens' => 2048,
                'response_format' => 'text',
                'seed' => 1,
            ])
        );
        $result = (new GenerationResultBuilder)->buildSuccessFromRequest(
            $request,
            new ProviderExecutionReference([
                'provider_code' => 'stub.deterministic',
                'execution_id' => 'exec_atomic',
                'started_at_utc' => gmdate('Y-m-d H:i:s'),
                'completed_at_utc' => gmdate('Y-m-d H:i:s'),
            ]),
            [new GeneratedArtifactReference([
                'artifact_id' => 'art_atomic',
                'content_hash' => str_repeat('d', 64),
            ])],
            ['duration_ms' => 2]
        );
        $preview = new ArticlePreview([
            'preview_id' => 'apv_'.substr(hash('sha256', $result->resultId().'|'.$request->requestId()), 0, 24),
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'announcement_id' => $ann->id,
            'request_id' => $request->requestId(),
            'result_id' => $result->resultId(),
            'result_hash' => $result->resultHash(),
            'title' => 'Atomic',
            'body' => 'Atomic body',
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);

        DB::transaction(function () use ($results, $previews, $organization, $project, $result, $preview) {
            $this->assertTrue($results->save($organization->id, $project->id, $result));
            $this->assertTrue($previews->save($preview));
        });

        $this->assertNotNull($results->findById($organization->id, $project->id, $result->resultId()));
        $this->assertNotNull($previews->findById($organization->id, $project->id, $preview->previewId()));
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
            'identity_hash' => hash('sha256', $title.'|'.$projectId),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/'.Str::slug($title),
            'raw_title' => $title,
            'content_hash' => hash('sha256', $title),
            'raw_payload' => ['title' => $title, 'summary' => $title],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
