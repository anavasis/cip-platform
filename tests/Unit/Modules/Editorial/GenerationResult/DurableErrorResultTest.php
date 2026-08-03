<?php

namespace Tests\Unit\Modules\Editorial\GenerationResult;

use App\Modules\Editorial\Application\AnnouncementSnapshotMapper;
use App\Modules\Editorial\Application\GenerationOrchestrator;
use App\Modules\Editorial\Application\NullGenerationDiagnostics;
use App\Modules\Editorial\Domain\Article\InMemoryArticlePreviewRepository;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintValidator;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestValidator;
use App\Modules\Editorial\Domain\GenerationResult\EditorialErrorCodes;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultBuilder;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultStatus;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultValidator;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptContext\PromptContextValidator;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageValidator;
use Tests\TestCase;

class DurableErrorResultTest extends TestCase
{
    private function item(): array
    {
        return [
            'id' => '66666666-6666-6666-6666-666666666666',
            'organization_id' => '77777777-7777-7777-7777-777777777777',
            'project_id' => '88888888-8888-8888-8888-888888888888',
            'source_id' => '99999999-9999-9999-9999-999999999999',
            'raw_title' => 'Error cases',
            'canonical_url' => 'https://example.test/e',
            'source_content_hash' => str_repeat('ef', 32),
            'announcement_revision_no' => 1,
            'language' => 'en',
            'raw_payload' => json_encode(['summary' => 'Error summary.']),
        ];
    }

    private function orchestrator(AiProviderInterface $provider): GenerationOrchestrator
    {
        return new GenerationOrchestrator(
            new AnnouncementSnapshotMapper,
            new ContentBlueprintBuilder,
            new ContentBlueprintValidator,
            new PromptContextBuilder,
            new PromptContextValidator,
            new PromptPackageBuilder,
            new PromptPackageValidator,
            new GenerationRequestBuilder,
            new GenerationRequestValidator,
            $provider,
            new GenerationResultBuilder,
            new GenerationResultValidator,
            new InMemoryArticlePreviewRepository,
            new NullGenerationDiagnostics,
        );
    }

    public function test_provider_ok_false_builds_durable_error_result(): void
    {
        $provider = new class implements AiProviderInterface
        {
            public function generate(GenerationRequest $request): array
            {
                return [
                    'ok' => false,
                    'provider_code' => 'stub.fail',
                    'execution_id' => 'exec_1',
                    'duration_ms' => 2,
                    'error_code' => EditorialErrorCodes::PROVIDER_ERROR,
                    'error_message' => 'rejected',
                ];
            }
        };
        $out = $this->orchestrator($provider)->generateFromAnnouncement($this->item());
        $this->assertFalse($out['ok']);
        $this->assertSame(EditorialErrorCodes::PROVIDER_ERROR, $out['error_code']);
        $this->assertSame(GenerationResultStatus::ERROR, $out['result']->status());
        $this->assertSame($out['request']->requestId(), $out['result']->requestId());
        $this->assertArrayNotHasKey('preview', $out);
    }

    public function test_provider_exception_builds_error_result(): void
    {
        $provider = new class implements AiProviderInterface
        {
            public function generate(GenerationRequest $request): array
            {
                throw new \RuntimeException('boom');
            }
        };
        $out = $this->orchestrator($provider)->generateFromAnnouncement($this->item());
        $this->assertFalse($out['ok']);
        $this->assertSame(EditorialErrorCodes::PROVIDER_EXCEPTION, $out['error_code']);
        $this->assertSame(GenerationResultStatus::ERROR, $out['result']->status());
        $this->assertSame('Provider execution failed.', $out['result']->errorMessage());
    }

    public function test_missing_content_text_builds_error_result(): void
    {
        $provider = new class implements AiProviderInterface
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
        $out = $this->orchestrator($provider)->generateFromAnnouncement($this->item());
        $this->assertFalse($out['ok']);
        $this->assertSame(EditorialErrorCodes::PROVIDER_CONTENT_TEXT_REQUIRED, $out['error_code']);
        $this->assertSame(GenerationResultStatus::ERROR, $out['result']->status());
    }

    public function test_invalid_provider_payload_builds_error_result(): void
    {
        $provider = $this->createMock(AiProviderInterface::class);
        $provider->method('generate')->willReturnCallback(function () {
            /** @phpstan-ignore-next-line intentionally invalid for runtime guard */
            return 123;
        });

        $out = $this->orchestrator($provider)->generateFromAnnouncement($this->item());
        $this->assertFalse($out['ok']);
        // Typed interface may surface as provider_exception; accept either stable terminal code with ERROR result when request exists.
        $this->assertContains($out['error_code'], [
            EditorialErrorCodes::PROVIDER_PAYLOAD_INVALID,
            EditorialErrorCodes::PROVIDER_EXCEPTION,
        ]);
        if (isset($out['result'])) {
            $this->assertSame(GenerationResultStatus::ERROR, $out['result']->status());
        }
    }

    public function test_error_codes_classification_is_explicit(): void
    {
        $this->assertTrue(EditorialErrorCodes::isPermanent(EditorialErrorCodes::BLUEPRINT_INVALID));
        $this->assertTrue(EditorialErrorCodes::isPermanent(EditorialErrorCodes::PROVIDER_CONTENT_TEXT_REQUIRED));
        $this->assertTrue(EditorialErrorCodes::isPermanent(EditorialErrorCodes::PROVIDER_ERROR));
        $this->assertFalse(EditorialErrorCodes::isRetryable(EditorialErrorCodes::EDITORIAL_JOB_FAILED));
        $this->assertTrue(EditorialErrorCodes::isRetryable(EditorialErrorCodes::ANNOUNCEMENT_LOCKED));
        $this->assertTrue(EditorialErrorCodes::isRetryable(EditorialErrorCodes::TRANSIENT_PERSISTENCE_FAILURE));
        $this->assertSame(
            EditorialErrorCodes::BLUEPRINT_INVALID,
            EditorialErrorCodes::fromMessage('blueprint_invalid:source_content_hash_required')
        );
    }
}
