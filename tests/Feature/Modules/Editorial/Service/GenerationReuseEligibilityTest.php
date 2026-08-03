<?php

namespace Tests\Feature\Modules\Editorial\Service;

use App\Application\Services\FeatureFlagService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestStatus;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResult;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultStatus;
use App\Modules\Editorial\Infrastructure\Persistence\Models\ArticlePreviewModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationRequestModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\PromptPackageModel;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentGenerationResultRepository;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerationReuseEligibilityTest extends TestCase
{
    public function test_identical_non_regenerate_call_reuses(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);

        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertTrue($second['reused'] ?? false);
        $this->assertSame($first['preview_id'], $second['preview_id']);
        $this->assertSame($first['request_id'], $second['request_id']);
        $this->assertSame(1, GenerationRequestModel::query()->where('project_id', $project->id)->count());
    }

    public function test_changed_content_hash_does_not_reuse(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($first['ok']);

        $ann->forceFill(['content_hash' => hash('sha256', 'changed-content')])->save();

        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($second['ok']);
        $this->assertFalse($second['reused'] ?? true);
        $this->assertNotSame($first['request_id'], $second['request_id']);
        $this->assertSame(2, GenerationRequestModel::query()->where('project_id', $project->id)->count());
    }

    public function test_changed_revision_does_not_reuse(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($first['ok']);

        $ann->forceFill(['revision_no' => ((int) $ann->revision_no) + 1])->save();

        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($second['ok']);
        $this->assertFalse($second['reused'] ?? true);
        $this->assertSame(2, GenerationRequestModel::query()->where('project_id', $project->id)->count());
    }

    public function test_latest_failed_request_with_older_successful_preview_does_not_reuse(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($first['ok']);

        $failedRequestId = 'gr_failed_'.Str::uuid();
        $payload = [
            'request_id' => $failedRequestId,
            'announcement_id' => $ann->id,
            'lineage_id' => 'regen_failed',
            'package_id' => GenerationRequestModel::query()->where('request_id', $first['request_id'])->value('package_id'),
            'package_hash' => str_repeat('a', 64),
            'model_reference' => ['model_id' => 'smce.stub.deterministic', 'model_version' => '1'],
            'parameters' => [
                'temperature' => 0.0,
                'max_output_tokens' => 2048,
                'top_p' => null,
                'seed' => 1,
                'response_format' => 'text',
            ],
            'status' => GenerationRequestStatus::READY,
            'request_hash' => hash('sha256', 'failed-'.$failedRequestId),
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ];
        GenerationRequestModel::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'announcement_id' => $ann->id,
            'request_id' => $failedRequestId,
            'package_id' => $payload['package_id'],
            'package_hash' => $payload['package_hash'],
            'request_hash' => $payload['request_hash'],
            'lineage_id' => $payload['lineage_id'],
            'status' => $payload['status'],
            'model_id' => 'smce.stub.deterministic',
            'model_version' => '1',
            'payload' => $payload,
            'created_at' => now()->addSecond(),
            'updated_at' => now()->addSecond(),
        ]);
        (new EloquentGenerationResultRepository)->save(
            $organization->id,
            $project->id,
            new GenerationResult([
                'result_id' => 'gres_failed_'.Str::uuid(),
                'request_id' => $failedRequestId,
                'request_hash' => $payload['request_hash'],
                'announcement_id' => $ann->id,
                'package_id' => $payload['package_id'],
                'package_hash' => $payload['package_hash'],
                'status' => GenerationResultStatus::ERROR,
                'provider_execution' => [
                    'execution_id' => 'exec_failed',
                    'provider_code' => 'stub',
                    'started_at_utc' => gmdate('Y-m-d H:i:s'),
                    'completed_at_utc' => gmdate('Y-m-d H:i:s'),
                ],
                'artifacts' => [],
                'error_code' => 'provider_error',
                'error_message' => 'failed',
                'duration_ms' => 1,
                'result_hash' => hash('sha256', 'failed-result'),
                'created_at_utc' => gmdate('Y-m-d H:i:s'),
            ]),
        );

        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($second['ok']);
        $this->assertFalse($second['reused'] ?? true);
        $this->assertNotSame($first['request_id'], $second['request_id']);
    }

    public function test_preview_request_mismatch_does_not_reuse(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($first['ok']);

        ArticlePreviewModel::query()
            ->where('preview_id', $first['preview_id'])
            ->update(['request_id' => 'gr_mismatched']);

        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($second['ok']);
        $this->assertFalse($second['reused'] ?? true);
    }

    public function test_request_result_mismatch_does_not_reuse(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($first['ok']);

        ArticlePreviewModel::query()
            ->where('preview_id', $first['preview_id'])
            ->update(['result_id' => 'gres_mismatched']);

        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($second['ok']);
        $this->assertFalse($second['reused'] ?? true);
    }

    public function test_error_result_does_not_reuse(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($first['ok']);

        $row = GenerationResultModel::query()->where('result_id', $first['result_id'])->firstOrFail();
        $payload = $row->payload;
        $payload['status'] = GenerationResultStatus::ERROR;
        $payload['error_code'] = 'provider_error';
        $row->forceFill(['status' => GenerationResultStatus::ERROR, 'payload' => $payload])->save();

        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($second['ok']);
        $this->assertFalse($second['reused'] ?? true);
    }

    public function test_cross_project_preview_never_reused(): void
    {
        [$organization, $projectA, $annA, $owner] = $this->seedReady('Project A', 'ann-a');
        $projectB = $this->createProject($organization->id, $owner->id, 'Project B');
        $sourceB = $this->createSource($organization->id, $projectB->id, 'source-b');
        $annB = $this->createAnnouncement($organization->id, $projectB->id, $sourceB->id, 'Ann B');
        $this->enableEditorial($organization->id, $projectB->id);

        $service = app(GenerateArticlePreviewService::class);
        $inA = $service->generate($organization->id, $projectA->id, $annA->id, $owner->id);
        $inB = $service->generate($organization->id, $projectB->id, $annB->id, $owner->id);

        $this->assertTrue($inA['ok']);
        $this->assertTrue($inB['ok']);
        $this->assertFalse($inB['reused'] ?? true);
        $this->assertNotSame($inA['preview_id'], $inB['preview_id']);
        $this->assertSame(1, ArticlePreviewModel::query()->where('project_id', $projectA->id)->count());
        $this->assertSame(1, ArticlePreviewModel::query()->where('project_id', $projectB->id)->count());
    }

    public function test_regenerate_never_reuses(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $regen = $service->generate($organization->id, $project->id, $ann->id, $owner->id, regenerate: true);

        $this->assertTrue($first['ok']);
        $this->assertTrue($regen['ok']);
        $this->assertFalse($regen['reused'] ?? true);
        $this->assertNotSame($first['request_id'], $regen['request_id']);
        $this->assertSame(2, GenerationRequestModel::query()->where('project_id', $project->id)->count());
    }

    public function test_model_mismatch_does_not_reuse(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($first['ok']);

        $requestRow = GenerationRequestModel::query()->where('request_id', $first['request_id'])->firstOrFail();
        $payload = $requestRow->payload;
        $payload['model_reference']['model_id'] = 'other.model';
        $requestRow->forceFill([
            'model_id' => 'other.model',
            'payload' => $payload,
        ])->save();

        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($second['ok']);
        $this->assertFalse($second['reused'] ?? true);
    }

    public function test_template_mismatch_does_not_reuse(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($first['ok']);

        $packageRow = PromptPackageModel::query()
            ->where('package_id', GenerationRequestModel::query()->where('request_id', $first['request_id'])->value('package_id'))
            ->firstOrFail();
        $packagePayload = $packageRow->payload;
        $packagePayload['template_reference']['template_id'] = 'other.template';
        $packageRow->forceFill([
            'template_id' => 'other.template',
            'payload' => $packagePayload,
        ])->save();

        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($second['ok']);
        $this->assertFalse($second['reused'] ?? true);
    }

    public function test_parameter_mismatch_does_not_reuse(): void
    {
        [$organization, $project, $ann, $owner] = $this->seedReady();
        $service = app(GenerateArticlePreviewService::class);
        $first = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($first['ok']);

        $req = GenerationRequestModel::query()->where('request_id', $first['request_id'])->firstOrFail();
        $reqPayload = $req->payload;
        $reqPayload['parameters']['seed'] = 99;
        $req->forceFill(['payload' => $reqPayload])->save();

        $second = $service->generate($organization->id, $project->id, $ann->id, $owner->id);
        $this->assertTrue($second['ok']);
        $this->assertFalse($second['reused'] ?? true);
    }

    /**
     * @return array{0: mixed, 1: Project, 2: Announcement, 3: mixed}
     */
    private function seedReady(string $projectName = 'Reuse Project', string $slug = 'reuse-source'): array
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, $projectName);
        $source = $this->createSource($organization->id, $project->id, $slug);
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Reuse Title');
        $this->enableEditorial($organization->id, $project->id);

        return [$organization, $project, $ann, $owner];
    }

    private function enableEditorial(string $organizationId, string $projectId): void
    {
        $flags = app(FeatureFlagService::class);
        foreach ([CapabilityGate::EDITORIAL, CapabilityGate::EDITORIAL_GENERATION] as $key) {
            $flags->upsert(
                $key,
                true,
                FeatureFlagScope::Project,
                null,
                $organizationId,
                $projectId,
            );
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
