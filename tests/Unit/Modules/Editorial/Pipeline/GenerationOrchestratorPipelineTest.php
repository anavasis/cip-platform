<?php

namespace Tests\Unit\Modules\Editorial\Pipeline;

use App\Modules\Editorial\Application\AnnouncementSnapshotMapper;
use App\Modules\Editorial\Application\GenerationOrchestrator;
use App\Modules\Editorial\Application\NullGenerationDiagnostics;
use App\Modules\Editorial\Domain\Article\ArticlePreview;
use App\Modules\Editorial\Domain\Article\InMemoryArticlePreviewRepository;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintValidator;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestValidator;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultBuilder;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultStatus;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultValidator;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptContext\PromptContextValidator;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageValidator;
use App\Modules\Editorial\Infrastructure\Generation\StubAiProvider;
use ReflectionClass;
use Tests\TestCase;

class GenerationOrchestratorPipelineTest extends TestCase
{
    private function announcementItem(): array
    {
        return [
            'id' => '66666666-6666-6666-6666-666666666666',
            'organization_id' => '77777777-7777-7777-7777-777777777777',
            'project_id' => '88888888-8888-8888-8888-888888888888',
            'source_id' => '99999999-9999-9999-9999-999999999999',
            'raw_title' => 'Pipeline announcement',
            'canonical_url' => 'https://example.test/a',
            'source_content_hash' => str_repeat('ab', 32),
            'announcement_revision_no' => 2,
            'language' => 'en',
            'raw_payload' => json_encode(['summary' => 'Pipeline summary body.']),
        ];
    }

    private function makeOrchestrator(?AiProviderInterface $provider = null): array
    {
        $previews = new InMemoryArticlePreviewRepository;
        $orchestrator = new GenerationOrchestrator(
            new AnnouncementSnapshotMapper,
            new ContentBlueprintBuilder,
            new ContentBlueprintValidator,
            new PromptContextBuilder,
            new PromptContextValidator,
            new PromptPackageBuilder,
            new PromptPackageValidator,
            new GenerationRequestBuilder,
            new GenerationRequestValidator,
            $provider ?? new StubAiProvider,
            new GenerationResultBuilder,
            new GenerationResultValidator,
            $previews,
            new NullGenerationDiagnostics,
        );

        return [$orchestrator, $previews];
    }

    public function test_full_pipeline_order_and_preview_behavior(): void
    {
        [$orchestrator, $previews] = $this->makeOrchestrator();
        $out = $orchestrator->generateFromAnnouncement($this->announcementItem());

        $this->assertTrue($out['ok']);
        $this->assertSame([
            'build_001' => true,
            'build_002' => true,
            'build_003' => true,
            'build_004' => true,
            'provider' => true,
            'build_005' => true,
            'preview_built' => true,
            'preview_stored' => false,
        ], $out['stages']);

        /** @var ArticlePreview $preview */
        $preview = $out['preview'];
        $this->assertStringStartsWith('apv_', $preview->previewId());
        $this->assertSame(28, strlen($preview->previewId()));
        $this->assertSame('Pipeline announcement', $preview->title());
        $this->assertStringContainsString('Stub article preview', $preview->body());
        $this->assertSame($this->announcementItem()['organization_id'], $preview->organizationId());
        $this->assertSame($this->announcementItem()['project_id'], $preview->projectId());

        $expectedId = 'apv_'.substr(hash('sha256', $out['result_id'].'|'.$out['request_id']), 0, 24);
        $this->assertSame($expectedId, $preview->previewId());
        // Orchestrator must not persist the preview.
        $this->assertNull($previews->findById($preview->organizationId(), $preview->projectId(), $preview->previewId()));
        $source = file_get_contents((new ReflectionClass(GenerationOrchestrator::class))->getFileName());
        $this->assertStringNotContainsString('previewRepository->save', $source);
    }

    public function test_preview_title_fallback_constant_present_in_orchestrator(): void
    {
        $source = file_get_contents((new ReflectionClass(GenerationOrchestrator::class))->getFileName());
        $this->assertStringContainsString("'Untitled'", $source);
        $preview = new ArticlePreview([
            'preview_id' => 'apv_'.str_repeat('a', 24),
            'organization_id' => 'o',
            'project_id' => 'p',
            'announcement_id' => 'a',
            'request_id' => 'gr_x',
            'result_id' => 'gres_x',
            'result_hash' => str_repeat('b', 64),
            'title' => 'Untitled',
            'body' => 'body',
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->assertSame('Untitled', $preview->title());
    }

    public function test_provider_ok_false_maps_to_error_result_without_preview(): void
    {
        $failing = new class implements AiProviderInterface
        {
            public function generate(GenerationRequest $request): array
            {
                return [
                    'ok' => false,
                    'provider_code' => 'stub.fail',
                    'execution_id' => 'exec_fail',
                    'duration_ms' => 2,
                    'error_code' => 'provider_rejected',
                    'error_message' => 'nope',
                ];
            }
        };
        [$orchestrator, $previews] = $this->makeOrchestrator($failing);
        $out = $orchestrator->generateFromAnnouncement($this->announcementItem());
        $this->assertFalse($out['ok']);
        $this->assertTrue($out['stages']['build_005']);
        $this->assertFalse($out['stages']['preview_stored']);
        $this->assertSame(GenerationResultStatus::ERROR, $out['result']->status());
        $this->assertNull($previews->findLatestForAnnouncement(
            $this->announcementItem()['organization_id'],
            $this->announcementItem()['project_id'],
            $this->announcementItem()['id'],
        ));
    }

    public function test_missing_content_text_prevents_preview(): void
    {
        $bad = new class implements AiProviderInterface
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
                    'content_hash' => str_repeat('3', 64),
                    'mime_type' => 'text/plain',
                ];
            }
        };
        [$orchestrator] = $this->makeOrchestrator($bad);
        $out = $orchestrator->generateFromAnnouncement($this->announcementItem());
        $this->assertFalse($out['ok']);
        $this->assertFalse($out['stages']['preview_stored']);
        $this->assertSame('provider_content_text_required', $out['error']);
    }

    public function test_orchestrator_depends_on_interface_not_stub(): void
    {
        $ref = new ReflectionClass(GenerationOrchestrator::class);
        $ctor = $ref->getConstructor();
        $params = $ctor->getParameters();
        $providerParam = null;
        foreach ($params as $param) {
            if ($param->getName() === 'aiProvider') {
                $providerParam = $param;
                break;
            }
        }
        $this->assertNotNull($providerParam);
        $this->assertSame(AiProviderInterface::class, $providerParam->getType()->getName());
        $source = file_get_contents($ref->getFileName());
        $this->assertDoesNotMatchRegularExpression('/^use .+StubAiProvider/m', $source);
        $this->assertStringNotContainsString('Infrastructure\\Generation\\StubAiProvider', $source);
    }
}
