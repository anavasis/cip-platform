<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Delivery\Application\WordPressDraftPublisher;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\Article\ArticlePreviewRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultRepositoryInterface;
use App\Modules\Intelligence\Application\ContentIntelligencePlanner;
use App\Modules\Intelligence\Application\EntityLifecycleService;
use App\Modules\Intelligence\Domain\ContentIntelligencePlan;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\EntityAnnouncementBindingModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PreviewWebController extends Controller
{
    private const LIFECYCLE_OPTIONS = [
        'open',
        'in_progress',
        'results',
        'objections',
        'final_results',
        'verification_required',
        'completed',
        'archived',
    ];

    public function __construct(
        private readonly ArticlePreviewRepositoryInterface $previews,
        private readonly GenerationResultRepositoryInterface $results,
        private readonly GenerateArticlePreviewService $generator,
        private readonly ContentIntelligencePlanner $planner,
        private readonly WordPressDraftPublisher $wordPressDraftPublisher,
        private readonly EntityLifecycleService $lifecycleService,
    ) {}

    public function show(Announcement $announcement): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $preview = $this->previews->findLatestForAnnouncement($org->id, $project->id, $announcement->id);
        abort_if($preview === null, 404, 'Preview not available.');
        $result = $this->results->findById($org->id, $project->id, $preview->resultId());

        $plan = $this->planner->planForAnnouncement($org->id, $project->id, $announcement);
        $delivery = $this->buildDeliveryPanel($announcement, $plan, $org->id, $project->id);

        return view('app.preview.show', [
            'announcement' => $announcement,
            'preview' => $preview,
            'result' => $result,
            'delivery' => $delivery,
        ]);
    }

    public function download(Announcement $announcement): Response
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $preview = $this->previews->findLatestForAnnouncement($org->id, $project->id, $announcement->id);
        abort_if($preview === null, 404);
        $markdown = '# '.$preview->title()."\n\n".$preview->body()."\n";

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="article-'.$announcement->id.'.md"',
        ]);
    }

    public function regenerate(Announcement $announcement): RedirectResponse
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        try {
            $out = $this->generator->generate(
                $org->id,
                $project->id,
                $announcement->id,
                request()->user()?->id,
                null,
                true,
                false,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['generation' => $e->getMessage()]);
        }
        if (($out['ok'] ?? false) !== true) {
            return back()->withErrors(['generation' => (string) ($out['error_code'] ?? 'generation_failed')]);
        }

        return redirect()->route('app.preview.show', $announcement)->with('status', 'Preview regenerated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeliveryPanel(
        Announcement $announcement,
        ContentIntelligencePlan $plan,
        string $organizationId,
        string $projectId,
    ): array {
        $entity = null;
        $entityId = trim((string) ($plan->entityId() ?? ''));
        if ($entityId !== '') {
            $entity = ContentEntityModel::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->where('entity_id', $entityId)
                ->first();
        }

        $wordpressAvailability = $this->wordPressDraftPublisher->availability($organizationId, $projectId);
        $wordpressAvailable = ($wordpressAvailability['available'] ?? false) === true;

        $packageUnavailableReason = null;
        $canDownloadPackage = false;
        if (! $plan->isResolved()) {
            $packageUnavailableReason = 'plan_not_resolved';
        } elseif ($plan->action() === ContentIntelligencePlan::ACTION_NO_PUBLISH) {
            $packageUnavailableReason = 'plan_no_publish';
        } elseif ($entityId === '') {
            $packageUnavailableReason = 'entity_id_missing';
        } else {
            $canDownloadPackage = true;
        }

        $canCreateWordPressDraft = $canDownloadPackage
            && $plan->action() === ContentIntelligencePlan::ACTION_CREATE_NEW
            && $entity !== null
            && $wordpressAvailable;

        $boundToEntity = $entity !== null && EntityAnnouncementBindingModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('content_entity_id', $entity->id)
            ->where('announcement_id', $announcement->id)
            ->exists();

        $hubReleaseUnavailableReason = null;
        $canReleaseToHub = false;
        if ($entity === null) {
            $hubReleaseUnavailableReason = 'entity_not_found';
        } elseif (! $plan->isResolved()) {
            $hubReleaseUnavailableReason = 'plan_not_resolved';
        } elseif (! $boundToEntity) {
            $hubReleaseUnavailableReason = 'announcement_not_bound_to_entity';
        } else {
            $canReleaseToHub = true;
        }

        $hubExclusionReason = null;
        $hubEligibilityLabel = 'unknown';
        if ($entity !== null) {
            $evaluation = $this->lifecycleService->evaluate($entity, now(), 168);
            $confirmedBinding = RemotePostBindingModel::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->where('content_entity_id', $entity->id)
                ->where('remote_system', 'wordpress')
                ->whereNotNull('confirmed_at')
                ->where('canonical_url', '!=', '')
                ->first();

            if (($evaluation['is_public_eligible'] ?? false) === true && $confirmedBinding !== null) {
                $hubEligibilityLabel = 'eligible';
            } else {
                $hubEligibilityLabel = 'excluded';
                if (($evaluation['effective_verification_status'] ?? '') !== EntityLifecycleService::VERIFICATION_VERIFIED) {
                    $hubExclusionReason = 'verification_not_verified_or_stale';
                } elseif ($entity->hub_member !== true || $entity->publish_eligible !== true) {
                    $hubExclusionReason = 'hub_member_or_publish_eligible_false';
                } elseif (($evaluation['effective_lifecycle_status'] ?? '') === EntityLifecycleService::LIFECYCLE_VERIFICATION_REQUIRED) {
                    $hubExclusionReason = 'effective_lifecycle_verification_required';
                } elseif ($confirmedBinding === null) {
                    $hubExclusionReason = 'missing_confirmed_binding_or_canonical_url';
                } else {
                    $hubExclusionReason = 'hub_rules_exclude_entity';
                }
            }
        } else {
            $hubEligibilityLabel = 'no_entity';
        }

        $suggestedCanonicalUrl = $plan->canonicalTargetUrl() ?? '';
        if ($entity !== null) {
            $remoteBinding = RemotePostBindingModel::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->where('content_entity_id', $entity->id)
                ->where('remote_system', 'wordpress')
                ->first();
            if ($remoteBinding !== null && trim((string) $remoteBinding->canonical_url) !== '') {
                $candidate = trim((string) $remoteBinding->canonical_url);
                if (! str_contains($candidate, 'pending.local')) {
                    $suggestedCanonicalUrl = $candidate;
                }
            }
        }

        return [
            'plan' => $plan,
            'entity' => $entity,
            'can_download_package' => $canDownloadPackage,
            'package_unavailable_reason' => $packageUnavailableReason,
            'can_create_wordpress_draft' => $canCreateWordPressDraft,
            'wordpress_available' => $wordpressAvailable,
            'wordpress_unavailable_reason' => $wordpressAvailability['reason'] ?? null,
            'can_release_to_hub' => $canReleaseToHub,
            'hub_release_unavailable_reason' => $hubReleaseUnavailableReason,
            'hub_eligibility_label' => $hubEligibilityLabel,
            'hub_exclusion_reason' => $hubExclusionReason,
            'lifecycle_options' => self::LIFECYCLE_OPTIONS,
            'suggested_canonical_url' => $suggestedCanonicalUrl,
        ];
    }
}
