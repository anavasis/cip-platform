<?php

namespace StudyMentor\ContentEngine\Generation;

use StudyMentor\ContentEngine\Article\ArticlePreview;
use StudyMentor\ContentEngine\Article\ArticlePreviewRepositoryInterface;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintBuilder;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintValidator;
use StudyMentor\ContentEngine\Editorial\AnnouncementSnapshotMapper;
use StudyMentor\ContentEngine\GenerationRequest\GenerationModelReference;
use StudyMentor\ContentEngine\GenerationRequest\GenerationParameters;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestBuilder;
use StudyMentor\ContentEngine\GenerationResult\GeneratedArtifactReference;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultBuilder;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultStatus;
use StudyMentor\ContentEngine\GenerationResult\ProviderExecutionReference;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
use StudyMentor\ContentEngine\PromptContext\PromptContextBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptTemplateReference;

defined('ABSPATH') || exit;

/**
 * Editorial Slice A orchestrator: Announcement → BUILD-001…005 → stub → preview.
 * No publishing, workflow, compliance, or real AI providers.
 */
final class GenerationOrchestrator
{
    private const TEMPLATE_ID = 'smce.editorial.slice_a';
    private const TEMPLATE_VERSION = '1.0.0';
    private const MODEL_ID = 'smce.stub.deterministic';
    private const MODEL_VERSION = '1';

    private $mapper;
    private $blueprintBuilder;
    private $blueprintValidator;
    private $promptContextBuilder;
    private $promptPackageBuilder;
    private $generationRequestBuilder;
    private $aiProvider;
    private $generationResultBuilder;
    private $previewRepository;
    private $platformDiagnostics;

    public function __construct(
        AnnouncementSnapshotMapper $mapper,
        ContentBlueprintBuilder $blueprintBuilder,
        ContentBlueprintValidator $blueprintValidator,
        PromptContextBuilder $promptContextBuilder,
        PromptPackageBuilder $promptPackageBuilder,
        GenerationRequestBuilder $generationRequestBuilder,
        AiProviderInterface $aiProvider,
        GenerationResultBuilder $generationResultBuilder,
        ArticlePreviewRepositoryInterface $previewRepository,
        PlatformDiagnostics $platformDiagnostics
    ) {
        $this->mapper = $mapper;
        $this->blueprintBuilder = $blueprintBuilder;
        $this->blueprintValidator = $blueprintValidator;
        $this->promptContextBuilder = $promptContextBuilder;
        $this->promptPackageBuilder = $promptPackageBuilder;
        $this->generationRequestBuilder = $generationRequestBuilder;
        $this->aiProvider = $aiProvider;
        $this->generationResultBuilder = $generationResultBuilder;
        $this->previewRepository = $previewRepository;
        $this->platformDiagnostics = $platformDiagnostics;
    }

    /**
     * @param array<string, mixed> $announcementItem Source item row (or compatible array).
     * @return array<string, mixed>
     */
    public function generateFromAnnouncement(array $announcementItem)
    {
        $stages = array(
            'build_001' => false,
            'build_002' => false,
            'build_003' => false,
            'build_004' => false,
            'stub_provider' => false,
            'build_005' => false,
            'preview_stored' => false,
        );

        $announcementId = 0;
        if (isset($announcementItem['id'])) {
            $announcementId = (int) $announcementItem['id'];
        } elseif (isset($announcementItem['announcement_id'])) {
            $announcementId = (int) $announcementItem['announcement_id'];
        }

        try {
            $snapshot = $this->mapper->fromSourceItem($announcementItem);

            $blueprint = $this->blueprintBuilder->buildFromAnnouncement($snapshot);
            $blueprintCheck = $this->blueprintValidator->validate($blueprint);
            if ($blueprintCheck['valid'] !== true) {
                throw new \InvalidArgumentException(
                    'blueprint_invalid:' . implode(',', $blueprintCheck['errors'])
                );
            }
            $stages['build_001'] = true;

            $context = $this->promptContextBuilder->buildFromAnnouncementAndBlueprint(
                $snapshot,
                $blueprint
            );
            $stages['build_002'] = true;

            $blueprintReference = $this->promptPackageBuilder->blueprintReferenceFromContext($context);
            $templateReference = new PromptTemplateReference(array(
                'template_id' => self::TEMPLATE_ID,
                'template_version' => self::TEMPLATE_VERSION,
            ));
            $package = $this->promptPackageBuilder->buildFromContextAndBlueprint(
                $context,
                $blueprintReference,
                $templateReference
            );
            $stages['build_003'] = true;

            $modelReference = new GenerationModelReference(array(
                'model_id' => self::MODEL_ID,
                'model_version' => self::MODEL_VERSION,
            ));
            $parameters = new GenerationParameters(array(
                'temperature' => 0.0,
                'max_output_tokens' => 2048,
                'response_format' => GenerationParameters::FORMAT_TEXT,
                'seed' => 1,
            ));
            $request = $this->generationRequestBuilder->buildFromPackage(
                $package,
                $modelReference,
                $parameters
            );
            $stages['build_004'] = true;

            $providerOut = $this->aiProvider->generate($request);
            $stages['stub_provider'] = true;

            $now = $this->utcNow();
            $execution = new ProviderExecutionReference(array(
                'execution_id' => isset($providerOut['execution_id'])
                    ? (string) $providerOut['execution_id']
                    : 'stub_exec_missing',
                'provider_code' => isset($providerOut['provider_code'])
                    ? (string) $providerOut['provider_code']
                    : StubAiProvider::PROVIDER_CODE,
                'started_at_utc' => $now,
                'completed_at_utc' => $now,
            ));

            $artifact = array(
                'artifact_id' => isset($providerOut['artifact_id'])
                    ? (string) $providerOut['artifact_id']
                    : 'stub_art_missing',
                'artifact_kind' => isset($providerOut['artifact_kind'])
                    ? (string) $providerOut['artifact_kind']
                    : GeneratedArtifactReference::KIND_CONTENT_CANDIDATE,
                'content_hash' => isset($providerOut['content_hash'])
                    ? (string) $providerOut['content_hash']
                    : '',
                'mime_type' => isset($providerOut['mime_type'])
                    ? (string) $providerOut['mime_type']
                    : 'text/plain',
            );

            $durationMs = isset($providerOut['duration_ms']) ? (int) $providerOut['duration_ms'] : 0;
            $result = $this->generationResultBuilder->buildSuccessFromRequest(
                $request,
                $execution,
                array($artifact),
                array('duration_ms' => $durationMs)
            );
            $stages['build_005'] = true;

            if ($result->status() !== GenerationResultStatus::SUCCESS) {
                $this->platformDiagnostics->recordLastGeneration(array(
                    'at' => $now,
                    'ok' => false,
                    'announcement_id' => $announcementId,
                    'error' => $result->errorMessage() !== ''
                        ? $result->errorMessage()
                        : $result->errorCode(),
                    'stages' => $stages,
                    'request_id' => $result->requestId(),
                    'result_id' => $result->resultId(),
                ));

                return array(
                    'ok' => false,
                    'error' => $result->errorMessage() !== ''
                        ? $result->errorMessage()
                        : 'Generation failed.',
                    'stages' => $stages,
                    'request_id' => $result->requestId(),
                    'result_id' => $result->resultId(),
                );
            }

            $body = isset($providerOut['body']) ? (string) $providerOut['body'] : '';
            $title = isset($snapshot['raw_title']) && $snapshot['raw_title'] !== ''
                ? (string) $snapshot['raw_title']
                : 'Untitled';

            $preview = new ArticlePreview(array(
                'preview_id' => 'apv_' . substr(
                    hash('sha256', $result->resultId() . '|' . $request->requestId()),
                    0,
                    24
                ),
                'announcement_id' => $announcementId > 0
                    ? $announcementId
                    : (int) $snapshot['announcement_id'],
                'request_id' => $request->requestId(),
                'result_id' => $result->resultId(),
                'result_hash' => $result->resultHash(),
                'title' => $title,
                'body' => $body,
                'created_at_utc' => $now,
            ));

            if (!$this->previewRepository->save($preview)) {
                throw new \RuntimeException('preview_save_failed');
            }
            $stages['preview_stored'] = true;

            $this->platformDiagnostics->recordLastGeneration(array(
                'at' => $now,
                'ok' => true,
                'announcement_id' => $preview->announcementId(),
                'blueprint_id' => $blueprint->blueprintId(),
                'request_id' => $request->requestId(),
                'result_id' => $result->resultId(),
                'preview_id' => $preview->previewId(),
                'stages' => $stages,
            ));

            return array(
                'ok' => true,
                'blueprint_id' => $blueprint->blueprintId(),
                'request_id' => $request->requestId(),
                'result_id' => $result->resultId(),
                'preview_id' => $preview->previewId(),
                'preview' => $preview,
                'result' => $result,
                'stages' => $stages,
            );
        } catch (\Throwable $e) {
            $this->platformDiagnostics->recordLastGeneration(array(
                'at' => $this->utcNow(),
                'ok' => false,
                'announcement_id' => $announcementId,
                'error' => $e->getMessage(),
                'stages' => $stages,
            ));

            return array(
                'ok' => false,
                'error' => $e->getMessage(),
                'stages' => $stages,
            );
        }
    }

    /**
     * @return string
     */
    private function utcNow()
    {
        if (function_exists('current_time')) {
            return (string) current_time('mysql', true);
        }

        return gmdate('Y-m-d H:i:s');
    }
}
