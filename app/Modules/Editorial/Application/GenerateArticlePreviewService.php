<?php

namespace App\Modules\Editorial\Application;

use App\Application\Services\EventBusService;
use App\Application\Services\JobEngineService;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Domain\Article\ArticlePreview;
use App\Modules\Editorial\Domain\Article\ArticlePreviewRepositoryInterface;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintRepositoryInterface;
use App\Modules\Editorial\Domain\Events\ArticlePreviewCreated;
use App\Modules\Editorial\Domain\Events\BlueprintCreated;
use App\Modules\Editorial\Domain\Events\GenerationCompleted;
use App\Modules\Editorial\Domain\Events\GenerationFailed;
use App\Modules\Editorial\Domain\Events\GenerationRequested;
use App\Modules\Editorial\Domain\Events\GenerationStarted;
use App\Modules\Editorial\Domain\Events\PromptContextCreated;
use App\Modules\Editorial\Domain\Events\PromptPackageCreated;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestStatus;
use App\Modules\Editorial\Domain\GenerationResult\EditorialErrorCodes;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResult;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultStatus;
use App\Modules\Editorial\Domain\PromptContext\PromptContextRepositoryInterface;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageRepositoryInterface;
use App\Modules\Editorial\Infrastructure\Jobs\GenerateArticlePreviewJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class GenerateArticlePreviewService
{
    private const EXPECTED_TEMPLATE_ID = 'smce.editorial.slice_a';

    private const EXPECTED_TEMPLATE_VERSION = '1.0.0';

    private const EXPECTED_MODEL_ID = 'smce.stub.deterministic';

    private const EXPECTED_MODEL_VERSION = '1';

    private const EXPECTED_TEMPERATURE = 0.0;

    private const EXPECTED_MAX_OUTPUT_TOKENS = 2048;

    private const EXPECTED_RESPONSE_FORMAT = 'text';

    private const EXPECTED_SEED = 1;

    public function __construct(
        private readonly GenerationOrchestrator $orchestrator,
        private readonly CapabilityGate $capabilityGate,
        private readonly ContentBlueprintRepositoryInterface $blueprints,
        private readonly PromptContextRepositoryInterface $contexts,
        private readonly PromptPackageRepositoryInterface $packages,
        private readonly GenerationRequestRepositoryInterface $requests,
        private readonly GenerationResultRepositoryInterface $results,
        private readonly ArticlePreviewRepositoryInterface $previews,
        private readonly EditorialDiagnostics $diagnostics,
        private readonly EventBusService $events,
        private readonly JobEngineService $jobs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(
        string $organizationId,
        string $projectId,
        string $announcementId,
        ?string $actorId = null,
        ?string $correlationId = null,
        bool $regenerate = false,
        bool $async = false,
    ): array {
        $organizationId = trim($organizationId);
        $projectId = trim($projectId);
        $announcementId = trim($announcementId);
        $correlationId = $correlationId !== null && trim($correlationId) !== ''
            ? trim($correlationId)
            : (string) Str::uuid();

        if (! $this->capabilityGate->generationAllowed($organizationId, $projectId)) {
            throw new RuntimeException('capability_disabled');
        }

        if ($async) {
            $platformJob = $this->jobs->create(
                'editorial.generate_article_preview',
                $organizationId,
                $projectId,
                [
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'announcement_id' => $announcementId,
                    'actor_id' => $actorId,
                    'correlation_id' => $correlationId,
                    'regenerate' => $regenerate,
                ],
            );
            GenerateArticlePreviewJob::dispatch($platformJob->id);

            return [
                'ok' => true,
                'queued' => true,
                'platform_job_id' => $platformJob->id,
                'correlation_id' => $correlationId,
            ];
        }

        $lock = Cache::lock(
            "editorial:project:{$projectId}:announcement:{$announcementId}",
            60,
        );

        if (! $lock->get()) {
            throw new RuntimeException('announcement_locked');
        }

        try {
            return $this->executeLocked(
                $organizationId,
                $projectId,
                $announcementId,
                $actorId,
                $correlationId,
                $regenerate,
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function executeLocked(
        string $organizationId,
        string $projectId,
        string $announcementId,
        ?string $actorId,
        string $correlationId,
        bool $regenerate,
    ): array {
        if (! $this->capabilityGate->generationAllowed($organizationId, $projectId)) {
            throw new RuntimeException('capability_disabled');
        }

        $announcement = Announcement::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->whereKey($announcementId)
            ->first();

        if ($announcement === null) {
            throw new RuntimeException('announcement_not_found');
        }

        if (! $regenerate) {
            $reuse = $this->tryReuseEligibleGeneration(
                $organizationId,
                $projectId,
                $announcementId,
                $announcement,
                $correlationId,
            );
            if ($reuse !== null) {
                return $reuse;
            }
        }

        $item = [
            'id' => $announcement->id,
            'announcement_id' => $announcement->id,
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'source_id' => $announcement->source_id,
            'raw_title' => $announcement->raw_title,
            'canonical_url' => $announcement->canonical_url,
            'source_guid' => $announcement->source_guid,
            'source_content_hash' => $announcement->content_hash,
            'content_hash' => $announcement->content_hash,
            'announcement_revision_no' => $announcement->revision_no,
            'revision_no' => $announcement->revision_no,
            'published_at_utc' => $announcement->source_published_at ? $announcement->source_published_at->utc()->format('Y-m-d H:i:s') : '',
            'language' => 'el',
            'raw_payload' => is_array($announcement->raw_payload)
                ? json_encode($announcement->raw_payload)
                : (string) $announcement->raw_payload,
        ];

        $options = ['correlation_id' => $correlationId];
        if ($regenerate) {
            $options['lineage_id'] = 'regen_'.Str::uuid()->toString();
        }

        $this->events->dispatch(new GenerationStarted(
            organizationId: $organizationId,
            projectId: $projectId,
            announcementId: $announcementId,
            actorId: $actorId,
            correlationId: $correlationId,
        ));

        $out = $this->orchestrator->generateFromAnnouncement($item, $options);

        if (($out['ok'] ?? false) !== true) {
            $this->persistPartial($organizationId, $projectId, $out);
            $requestId = isset($out['request']) ? $out['request']->requestId() : ($out['request_id'] ?? null);
            $resultId = isset($out['result']) ? $out['result']->resultId() : ($out['result_id'] ?? null);
            $errorCode = EditorialErrorCodes::fromMessage(
                isset($out['result'])
                    ? $out['result']->errorCode()
                    : (string) ($out['error_code'] ?? $out['error'] ?? EditorialErrorCodes::PROVIDER_ERROR)
            );

            $this->events->dispatch(new GenerationFailed(
                organizationId: $organizationId,
                projectId: $projectId,
                requestId: $requestId,
                resultId: $resultId,
                announcementId: $announcementId,
                errorCode: $errorCode,
                actorId: $actorId,
                correlationId: $correlationId,
            ));

            return [
                'ok' => false,
                'queued' => false,
                'error' => $errorCode,
                'error_code' => $errorCode,
                'correlation_id' => $correlationId,
                'request_id' => $requestId,
                'result_id' => $resultId,
                'stages' => $out['stages'] ?? [],
                'failure_event_emitted' => true,
            ];
        }

        // Hash-level idempotency: only reuse when the stored lineage still passes eligibility.
        $request = $out['request'];
        $existing = $this->requests->findByRequestHash($organizationId, $projectId, $request->requestHash());
        if ($existing !== null && ! $regenerate) {
            $existingPreview = $this->previews->findLatestForAnnouncement(
                $organizationId,
                $projectId,
                $announcementId,
            );
            if ($existingPreview !== null
                && $this->isReuseEligible(
                    $organizationId,
                    $projectId,
                    $announcementId,
                    $announcement,
                    $existingPreview,
                    $existing,
                )) {
                $existingResult = $this->results->findById(
                    $organizationId,
                    $projectId,
                    $existingPreview->resultId(),
                );

                return [
                    'ok' => true,
                    'queued' => false,
                    'reused' => true,
                    'correlation_id' => $correlationId,
                    'request_id' => $existing->requestId(),
                    'request_hash' => $existing->requestHash(),
                    'result_id' => $existingResult?->resultId(),
                    'preview_id' => $existingPreview->previewId(),
                    'blueprint_id' => $out['blueprint_id'] ?? null,
                ];
            }
        }

        DB::transaction(function () use ($organizationId, $projectId, $out, $actorId, $correlationId, $announcementId) {
            $blueprint = $out['blueprint'];
            $context = $out['context'];
            $package = $out['package'];
            $request = $out['request'];
            $result = $out['result'];
            $preview = $out['preview'];

            $this->blueprints->save($organizationId, $projectId, $blueprint);
            $this->contexts->save($organizationId, $projectId, $context);
            $this->packages->save($organizationId, $projectId, $package);
            $this->requests->save($organizationId, $projectId, $request);

            if (! $this->results->save($organizationId, $projectId, $result)) {
                throw new RuntimeException('generation_result_persist_failed');
            }
            if (! $this->previews->save($preview)) {
                throw new RuntimeException('preview_save_failed');
            }

            $pendingEvents = [
                new BlueprintCreated(
                    organizationId: $organizationId,
                    projectId: $projectId,
                    blueprintId: $blueprint->blueprintId(),
                    announcementId: $announcementId,
                    actorId: $actorId,
                    correlationId: $correlationId,
                ),
                new PromptContextCreated(
                    organizationId: $organizationId,
                    projectId: $projectId,
                    contextId: $context->contextId(),
                    contextHash: $context->contextHash(),
                    announcementId: $announcementId,
                    actorId: $actorId,
                    correlationId: $correlationId,
                ),
                new PromptPackageCreated(
                    organizationId: $organizationId,
                    projectId: $projectId,
                    packageId: $package->packageId(),
                    packageHash: $package->packageHash(),
                    announcementId: $announcementId,
                    actorId: $actorId,
                    correlationId: $correlationId,
                ),
                new GenerationRequested(
                    organizationId: $organizationId,
                    projectId: $projectId,
                    requestId: $request->requestId(),
                    requestHash: $request->requestHash(),
                    announcementId: $announcementId,
                    actorId: $actorId,
                    correlationId: $correlationId,
                ),
                new ArticlePreviewCreated(
                    organizationId: $organizationId,
                    projectId: $projectId,
                    previewId: $preview->previewId(),
                    resultId: $result->resultId(),
                    announcementId: $announcementId,
                    requestId: $request->requestId(),
                    actorId: $actorId,
                    correlationId: $correlationId,
                ),
                new GenerationCompleted(
                    organizationId: $organizationId,
                    projectId: $projectId,
                    requestId: $request->requestId(),
                    resultId: $result->resultId(),
                    resultHash: $result->resultHash(),
                    announcementId: $announcementId,
                    previewId: $preview->previewId(),
                    actorId: $actorId,
                    correlationId: $correlationId,
                ),
            ];

            DB::afterCommit(function () use ($pendingEvents) {
                foreach ($pendingEvents as $event) {
                    $this->events->dispatch($event);
                }
            });
        });

        $stages = $out['stages'] ?? [];
        $stages['preview_stored'] = true;

        return [
            'ok' => true,
            'queued' => false,
            'reused' => false,
            'correlation_id' => $correlationId,
            'request_id' => $out['request_id'],
            'request_hash' => $out['request']->requestHash(),
            'result_id' => $out['result_id'],
            'preview_id' => $out['preview_id'],
            'blueprint_id' => $out['blueprint_id'],
            'stages' => $stages,
        ];
    }

    /**
     * @param  array<string, mixed>  $out
     */
    private function persistPartial(string $organizationId, string $projectId, array $out): void
    {
        try {
            DB::transaction(function () use ($organizationId, $projectId, $out) {
                if (isset($out['blueprint'])) {
                    $this->blueprints->save($organizationId, $projectId, $out['blueprint']);
                }
                if (isset($out['context'])) {
                    $this->contexts->save($organizationId, $projectId, $out['context']);
                }
                if (isset($out['package'])) {
                    $this->packages->save($organizationId, $projectId, $out['package']);
                }
                if (isset($out['request'])) {
                    $this->requests->save($organizationId, $projectId, $out['request']);
                }
                if (isset($out['result'])) {
                    $this->results->save($organizationId, $projectId, $out['result']);
                }
            });
        } catch (\Throwable) {
            // best-effort persistence of error path
        }
    }

    /**
     * Input-aware idempotent reuse: latest preview must match the latest READY request,
     * its SUCCESS result, current announcement inputs, and stub binding constants.
     *
     * @return array<string, mixed>|null
     */
    private function tryReuseEligibleGeneration(
        string $organizationId,
        string $projectId,
        string $announcementId,
        Announcement $announcement,
        string $correlationId,
    ): ?array {
        $preview = $this->previews->findLatestForAnnouncement(
            $organizationId,
            $projectId,
            $announcementId,
        );
        $request = $this->requests->findLatestForAnnouncement(
            $organizationId,
            $projectId,
            $announcementId,
        );

        if ($preview === null || $request === null) {
            return null;
        }

        if (! $this->isReuseEligible(
            $organizationId,
            $projectId,
            $announcementId,
            $announcement,
            $preview,
            $request,
        )) {
            return null;
        }

        $result = $this->results->findById(
            $organizationId,
            $projectId,
            $preview->resultId(),
        );

        return [
            'ok' => true,
            'queued' => false,
            'reused' => true,
            'correlation_id' => $correlationId,
            'request_id' => $request->requestId(),
            'request_hash' => $request->requestHash(),
            'result_id' => $result?->resultId(),
            'preview_id' => $preview->previewId(),
            'blueprint_id' => null,
        ];
    }

    private function isReuseEligible(
        string $organizationId,
        string $projectId,
        string $announcementId,
        Announcement $announcement,
        ArticlePreview $preview,
        GenerationRequest $request,
    ): bool {
        if ($preview->organizationId() !== $organizationId
            || $preview->projectId() !== $projectId
            || $preview->announcementId() !== $announcementId) {
            return false;
        }

        if ($request->announcementId() !== $announcementId) {
            return false;
        }

        if ($preview->requestId() === '' || $preview->requestId() !== $request->requestId()) {
            return false;
        }

        if ($request->status() !== GenerationRequestStatus::READY) {
            return false;
        }

        if ($preview->resultId() === '' || $preview->title() === '' || $preview->body() === '') {
            return false;
        }

        $result = $this->results->findById($organizationId, $projectId, $preview->resultId());
        if ($result === null) {
            return false;
        }

        if ($result->status() !== GenerationResultStatus::SUCCESS) {
            return false;
        }

        if ($result->requestId() !== $request->requestId()
            || $result->resultId() !== $preview->resultId()) {
            return false;
        }

        $byRequest = $this->results->findByRequestId(
            $organizationId,
            $projectId,
            $request->requestId(),
        );
        if ($byRequest === null
            || $byRequest->resultId() !== $result->resultId()
            || $byRequest->status() !== GenerationResultStatus::SUCCESS) {
            return false;
        }

        if (! $this->requestBindingMatchesStubDefaults($request)) {
            return false;
        }

        $package = $this->packages->findById($organizationId, $projectId, $request->packageId());
        if ($package === null) {
            return false;
        }

        $template = $package->templateReference();
        if ($template->templateId() !== self::EXPECTED_TEMPLATE_ID
            || $template->templateVersion() !== self::EXPECTED_TEMPLATE_VERSION) {
            return false;
        }

        $context = $this->contexts->findById($organizationId, $projectId, $package->contextId());
        if ($context === null) {
            return false;
        }

        $currentHash = (string) $announcement->content_hash;
        $currentRevision = (int) $announcement->revision_no;
        if ($context->sourceContentHash() !== $currentHash
            || (int) $context->announcementRevisionNo() !== $currentRevision) {
            return false;
        }

        // Readable durable preview row still resolves for this lineage.
        $loaded = $this->previews->findById($organizationId, $projectId, $preview->previewId());
        if ($loaded === null
            || $loaded->requestId() !== $request->requestId()
            || $loaded->resultId() !== $result->resultId()
            || $loaded->body() === '') {
            return false;
        }

        return true;
    }

    private function requestBindingMatchesStubDefaults(GenerationRequest $request): bool
    {
        $model = $request->modelReference();
        if ($model->modelId() !== self::EXPECTED_MODEL_ID
            || $model->modelVersion() !== self::EXPECTED_MODEL_VERSION) {
            return false;
        }

        $parameters = $request->parameters();
        if ((float) $parameters->temperature() !== self::EXPECTED_TEMPERATURE) {
            return false;
        }
        if ((int) $parameters->maxOutputTokens() !== self::EXPECTED_MAX_OUTPUT_TOKENS) {
            return false;
        }
        if ($parameters->responseFormat() !== self::EXPECTED_RESPONSE_FORMAT) {
            return false;
        }
        if ((int) $parameters->seed() !== self::EXPECTED_SEED) {
            return false;
        }

        return true;
    }
}
