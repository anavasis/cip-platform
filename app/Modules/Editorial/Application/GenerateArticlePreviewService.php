<?php

namespace App\Modules\Editorial\Application;

use App\Application\Services\EventBusService;
use App\Application\Services\JobEngineService;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
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
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestRepositoryInterface;
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
            $existingPreview = $this->previews->findLatestForAnnouncement(
                $organizationId,
                $projectId,
                $announcementId,
            );
            $existingRequest = $this->requests->findLatestForAnnouncement(
                $organizationId,
                $projectId,
                $announcementId,
            );
            if ($existingPreview !== null && $existingRequest !== null) {
                $existingResult = $this->results->findByRequestId(
                    $organizationId,
                    $projectId,
                    $existingRequest->requestId(),
                );

                return [
                    'ok' => true,
                    'queued' => false,
                    'reused' => true,
                    'correlation_id' => $correlationId,
                    'request_id' => $existingRequest->requestId(),
                    'request_hash' => $existingRequest->requestHash(),
                    'result_id' => $existingResult?->resultId(),
                    'preview_id' => $existingPreview->previewId(),
                    'blueprint_id' => null,
                ];
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
            $errorCode = isset($out['result']) ? $out['result']->errorCode() : (string) ($out['error'] ?? 'generation_failed');

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
                'error' => (string) ($out['error'] ?? 'generation_failed'),
                'error_code' => $errorCode,
                'correlation_id' => $correlationId,
                'request_id' => $requestId,
                'result_id' => $resultId,
                'stages' => $out['stages'] ?? [],
            ];
        }

        // Idempotency: if same request_hash already exists, reuse stored result/preview
        $request = $out['request'];
        $existing = $this->requests->findByRequestHash($organizationId, $projectId, $request->requestHash());
        if ($existing !== null && ! $regenerate) {
            $existingResult = $this->results->findByRequestId($organizationId, $projectId, $existing->requestId());
            $existingPreview = $this->previews->findLatestForAnnouncement($organizationId, $projectId, $announcementId);

            return [
                'ok' => true,
                'queued' => false,
                'reused' => true,
                'correlation_id' => $correlationId,
                'request_id' => $existing->requestId(),
                'request_hash' => $existing->requestHash(),
                'result_id' => $existingResult?->resultId(),
                'preview_id' => $existingPreview?->previewId(),
                'blueprint_id' => $out['blueprint_id'] ?? null,
            ];
        }

        DB::transaction(function () use ($organizationId, $projectId, $out, $actorId, $correlationId, $announcementId) {
            $blueprint = $out['blueprint'];
            $context = $out['context'];
            $package = $out['package'];
            $request = $out['request'];
            $result = $out['result'];
            $preview = $out['preview'];

            $this->blueprints->save($organizationId, $projectId, $blueprint);
            $this->events->dispatch(new BlueprintCreated(
                organizationId: $organizationId,
                projectId: $projectId,
                blueprintId: $blueprint->blueprintId(),
                announcementId: $announcementId,
                actorId: $actorId,
                correlationId: $correlationId,
            ));

            $this->contexts->save($organizationId, $projectId, $context);
            $this->events->dispatch(new PromptContextCreated(
                organizationId: $organizationId,
                projectId: $projectId,
                contextId: $context->contextId(),
                contextHash: $context->contextHash(),
                announcementId: $announcementId,
                actorId: $actorId,
                correlationId: $correlationId,
            ));

            $this->packages->save($organizationId, $projectId, $package);
            $this->events->dispatch(new PromptPackageCreated(
                organizationId: $organizationId,
                projectId: $projectId,
                packageId: $package->packageId(),
                packageHash: $package->packageHash(),
                announcementId: $announcementId,
                actorId: $actorId,
                correlationId: $correlationId,
            ));

            $this->requests->save($organizationId, $projectId, $request);
            $this->events->dispatch(new GenerationRequested(
                organizationId: $organizationId,
                projectId: $projectId,
                requestId: $request->requestId(),
                requestHash: $request->requestHash(),
                announcementId: $announcementId,
                actorId: $actorId,
                correlationId: $correlationId,
            ));

            $this->results->save($organizationId, $projectId, $result);
            $this->previews->save($preview);

            $this->events->dispatch(new ArticlePreviewCreated(
                organizationId: $organizationId,
                projectId: $projectId,
                previewId: $preview->previewId(),
                resultId: $result->resultId(),
                announcementId: $announcementId,
                requestId: $request->requestId(),
                actorId: $actorId,
                correlationId: $correlationId,
            ));

            $this->events->dispatch(new GenerationCompleted(
                organizationId: $organizationId,
                projectId: $projectId,
                requestId: $request->requestId(),
                resultId: $result->resultId(),
                resultHash: $result->resultHash(),
                announcementId: $announcementId,
                previewId: $preview->previewId(),
                actorId: $actorId,
                correlationId: $correlationId,
            ));
        });

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
            'stages' => $out['stages'],
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
}
