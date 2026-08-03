<?php

namespace App\Modules\Editorial\Application;

use App\Modules\Editorial\Domain\Article\ArticlePreview;
use App\Modules\Editorial\Domain\Article\ArticlePreviewRepositoryInterface;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintValidator;
use App\Modules\Editorial\Domain\Contracts\GenerationDiagnosticsSink;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationModelReference;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationParameters;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestValidator;
use App\Modules\Editorial\Domain\GenerationResult\GeneratedArtifactReference;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultBuilder;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultStatus;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultValidator;
use App\Modules\Editorial\Domain\GenerationResult\ProviderExecutionReference;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptContext\PromptContextValidator;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageValidator;
use App\Modules\Editorial\Domain\PromptPackage\PromptTemplateReference;

/**
 * Editorial Slice A orchestrator: Announcement → BUILD-001…005 → provider → preview.
 * Depends on AiProviderInterface only.
 */
final class GenerationOrchestrator
{
    private const TEMPLATE_ID = 'smce.editorial.slice_a';

    private const TEMPLATE_VERSION = '1.0.0';

    private const MODEL_ID = 'smce.stub.deterministic';

    private const MODEL_VERSION = '1';

    public function __construct(
        private readonly AnnouncementSnapshotMapper $mapper,
        private readonly ContentBlueprintBuilder $blueprintBuilder,
        private readonly ContentBlueprintValidator $blueprintValidator,
        private readonly PromptContextBuilder $promptContextBuilder,
        private readonly PromptContextValidator $promptContextValidator,
        private readonly PromptPackageBuilder $promptPackageBuilder,
        private readonly PromptPackageValidator $promptPackageValidator,
        private readonly GenerationRequestBuilder $generationRequestBuilder,
        private readonly GenerationRequestValidator $generationRequestValidator,
        private readonly AiProviderInterface $aiProvider,
        private readonly GenerationResultBuilder $generationResultBuilder,
        private readonly GenerationResultValidator $generationResultValidator,
        private readonly ArticlePreviewRepositoryInterface $previewRepository,
        private readonly GenerationDiagnosticsSink $diagnostics,
    ) {}

    /**
     * @param  array<string, mixed>  $announcementItem
     * @return array<string, mixed>
     */
    public function generateFromAnnouncement(array $announcementItem, array $options = []): array
    {
        $stages = [
            'build_001' => false,
            'build_002' => false,
            'build_003' => false,
            'build_004' => false,
            'provider' => false,
            'build_005' => false,
            'preview_stored' => false,
        ];

        $announcementId = '';
        if (isset($announcementItem['id'])) {
            $announcementId = trim((string) $announcementItem['id']);
        } elseif (isset($announcementItem['announcement_id'])) {
            $announcementId = trim((string) $announcementItem['announcement_id']);
        }

        $organizationId = isset($announcementItem['organization_id'])
            ? trim((string) $announcementItem['organization_id'])
            : '';
        $projectId = isset($announcementItem['project_id'])
            ? trim((string) $announcementItem['project_id'])
            : '';

        try {
            $snapshot = $this->mapper->fromSourceItem($announcementItem);
            if ($organizationId === '') {
                $organizationId = (string) ($snapshot['organization_id'] ?? '');
            }
            if ($projectId === '') {
                $projectId = (string) ($snapshot['project_id'] ?? '');
            }

            $blueprint = $this->blueprintBuilder->buildFromAnnouncement($snapshot);
            $this->assertValid(
                $this->blueprintValidator->validate($blueprint),
                'blueprint_invalid'
            );
            $stages['build_001'] = true;

            $context = $this->promptContextBuilder->buildFromAnnouncementAndBlueprint(
                $snapshot,
                $blueprint
            );
            $this->assertValid(
                $this->promptContextValidator->validate($context),
                'prompt_context_invalid'
            );
            $stages['build_002'] = true;

            $blueprintReference = $this->promptPackageBuilder->blueprintReferenceFromContext($context);
            $templateReference = new PromptTemplateReference([
                'template_id' => self::TEMPLATE_ID,
                'template_version' => self::TEMPLATE_VERSION,
            ]);
            $package = $this->promptPackageBuilder->buildFromContextAndBlueprint(
                $context,
                $blueprintReference,
                $templateReference
            );
            $this->assertValid(
                $this->promptPackageValidator->validate($package),
                'prompt_package_invalid'
            );
            $stages['build_003'] = true;

            $modelReference = new GenerationModelReference([
                'model_id' => self::MODEL_ID,
                'model_version' => self::MODEL_VERSION,
            ]);
            $parameters = new GenerationParameters([
                'temperature' => 0.0,
                'max_output_tokens' => 2048,
                'response_format' => GenerationParameters::FORMAT_TEXT,
                'seed' => 1,
            ]);
            $requestOverrides = [];
            if (isset($options['lineage_id']) && trim((string) $options['lineage_id']) !== '') {
                $requestOverrides['lineage_id'] = trim((string) $options['lineage_id']);
            }
            $request = $this->generationRequestBuilder->buildFromPackage(
                $package,
                $modelReference,
                $parameters,
                $requestOverrides
            );
            $this->assertValid(
                $this->generationRequestValidator->validate($request),
                'generation_request_invalid'
            );
            $stages['build_004'] = true;

            $providerOut = $this->aiProvider->generate($request);
            if (! is_array($providerOut)) {
                throw new \InvalidArgumentException('provider_response_invalid');
            }
            $stages['provider'] = true;

            $now = $this->utcNow();
            $providerCode = isset($providerOut['provider_code'])
                ? trim((string) $providerOut['provider_code'])
                : '';
            if ($providerCode === '') {
                $providerCode = 'unknown';
            }

            $execution = new ProviderExecutionReference([
                'execution_id' => isset($providerOut['execution_id'])
                    ? (string) $providerOut['execution_id']
                    : 'exec_missing',
                'provider_code' => $providerCode,
                'started_at_utc' => $now,
                'completed_at_utc' => $now,
            ]);

            $providerOk = isset($providerOut['ok']) && $providerOut['ok'] === true;
            $durationMs = isset($providerOut['duration_ms']) ? (int) $providerOut['duration_ms'] : 0;

            if ($providerOk) {
                $artifact = [
                    'artifact_id' => isset($providerOut['artifact_id'])
                        ? (string) $providerOut['artifact_id']
                        : '',
                    'artifact_kind' => isset($providerOut['artifact_kind'])
                        ? (string) $providerOut['artifact_kind']
                        : GeneratedArtifactReference::KIND_CONTENT_CANDIDATE,
                    'content_hash' => isset($providerOut['content_hash'])
                        ? (string) $providerOut['content_hash']
                        : '',
                    'mime_type' => isset($providerOut['mime_type'])
                        ? (string) $providerOut['mime_type']
                        : 'text/plain',
                ];

                $result = $this->generationResultBuilder->buildSuccessFromRequest(
                    $request,
                    $execution,
                    [$artifact],
                    ['duration_ms' => $durationMs]
                );
            } else {
                $errorCode = isset($providerOut['error_code'])
                    ? trim((string) $providerOut['error_code'])
                    : '';
                if ($errorCode === '') {
                    $errorCode = 'provider_error';
                }
                $errorMessage = isset($providerOut['error_message'])
                    ? trim((string) $providerOut['error_message'])
                    : 'Provider reported failure.';

                $result = $this->generationResultBuilder->buildErrorFromRequest(
                    $request,
                    $execution,
                    $errorCode,
                    $errorMessage,
                    ['duration_ms' => $durationMs]
                );
            }

            $this->assertValid(
                $this->generationResultValidator->validate($result),
                'generation_result_invalid'
            );
            $stages['build_005'] = true;

            if ($result->status() !== GenerationResultStatus::SUCCESS) {
                $this->diagnostics->recordLastGeneration([
                    'at' => $now,
                    'ok' => false,
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'announcement_id' => $announcementId,
                    'error' => $result->errorMessage() !== ''
                        ? $result->errorMessage()
                        : $result->errorCode(),
                    'stages' => $stages,
                    'request_id' => $result->requestId(),
                    'result_id' => $result->resultId(),
                    'result_status' => $result->status(),
                    'provider_code' => $providerCode,
                    'duration_ms' => $durationMs,
                    'correlation_id' => $options['correlation_id'] ?? null,
                ]);

                return [
                    'ok' => false,
                    'error' => $result->errorMessage() !== ''
                        ? $result->errorMessage()
                        : 'Generation failed.',
                    'stages' => $stages,
                    'request_id' => $result->requestId(),
                    'result_id' => $result->resultId(),
                    'result' => $result,
                    'blueprint' => $blueprint,
                    'context' => $context,
                    'package' => $package,
                    'request' => $request,
                ];
            }

            $contentText = $this->providerNeutralContentText($providerOut);
            $title = isset($snapshot['raw_title']) && $snapshot['raw_title'] !== ''
                ? (string) $snapshot['raw_title']
                : 'Untitled';

            $resolvedAnnouncementId = $announcementId !== ''
                ? $announcementId
                : (string) $snapshot['announcement_id'];

            $preview = new ArticlePreview([
                'preview_id' => 'apv_'.substr(
                    hash('sha256', $result->resultId().'|'.$request->requestId()),
                    0,
                    24
                ),
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'announcement_id' => $resolvedAnnouncementId,
                'request_id' => $request->requestId(),
                'result_id' => $result->resultId(),
                'result_hash' => $result->resultHash(),
                'title' => $title,
                'body' => $contentText,
                'created_at_utc' => $now,
            ]);

            if (! $this->previewRepository->save($preview)) {
                throw new \RuntimeException('preview_save_failed');
            }
            $stages['preview_stored'] = true;

            $this->diagnostics->recordLastGeneration([
                'at' => $now,
                'ok' => true,
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'announcement_id' => $preview->announcementId(),
                'blueprint_id' => $blueprint->blueprintId(),
                'request_id' => $request->requestId(),
                'result_id' => $result->resultId(),
                'preview_id' => $preview->previewId(),
                'stages' => $stages,
                'result_status' => $result->status(),
                'provider_code' => $providerCode,
                'model_id' => self::MODEL_ID,
                'duration_ms' => $durationMs,
                'preview_available' => true,
                'correlation_id' => $options['correlation_id'] ?? null,
            ]);

            return [
                'ok' => true,
                'blueprint_id' => $blueprint->blueprintId(),
                'request_id' => $request->requestId(),
                'result_id' => $result->resultId(),
                'preview_id' => $preview->previewId(),
                'preview' => $preview,
                'result' => $result,
                'blueprint' => $blueprint,
                'context' => $context,
                'package' => $package,
                'request' => $request,
                'stages' => $stages,
            ];
        } catch (\Throwable $e) {
            $this->diagnostics->recordLastGeneration([
                'at' => $this->utcNow(),
                'ok' => false,
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'announcement_id' => $announcementId,
                'error' => $e->getMessage(),
                'stages' => $stages,
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'stages' => $stages,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $providerOut
     */
    private function providerNeutralContentText(array $providerOut): string
    {
        if (! isset($providerOut['content_text']) || ! is_scalar($providerOut['content_text'])) {
            throw new \InvalidArgumentException('provider_content_text_required');
        }

        return (string) $providerOut['content_text'];
    }

    /**
     * @param  array{valid?: bool, errors?: array<int, string>}  $check
     */
    private function assertValid(array $check, string $prefix): void
    {
        if (! isset($check['valid']) || $check['valid'] !== true) {
            $errors = isset($check['errors']) && is_array($check['errors'])
                ? implode(',', $check['errors'])
                : 'unknown';
            throw new \InvalidArgumentException($prefix.':'.$errors);
        }
    }

    private function utcNow(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
