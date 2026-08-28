<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Delivery\Application\PublishPackageBuilder;
use App\Modules\Delivery\Application\WordPressDraftPublisher;
use App\Modules\Editorial\Domain\Article\ArticlePreviewRepositoryInterface;
use App\Modules\Intelligence\Application\ContentIntelligencePlanner;
use App\Modules\Intelligence\Application\HubCandidateReleaseService;
use App\Modules\Intelligence\Domain\ContentIntelligencePlan;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeliveryWebController extends Controller
{
    public function __construct(
        private readonly ArticlePreviewRepositoryInterface $previews,
        private readonly ContentIntelligencePlanner $planner,
        private readonly PublishPackageBuilder $packageBuilder,
        private readonly WordPressDraftPublisher $wordPressDraftPublisher,
        private readonly HubCandidateReleaseService $hubRelease,
    ) {}

    public function downloadPackage(Announcement $announcement): Response|RedirectResponse
    {
        $context = $this->resolveDeliveryContext($announcement);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['preview' => $preview, 'plan' => $plan, 'entity' => $entity] = $context;

        try {
            $package = $this->packageBuilder->build($announcement, $plan, $preview, $entity);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['delivery' => $e->getMessage()]);
        }

        $filename = 'publish-package-'.$announcement->id.'.json';

        return response(
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }

    public function createWordPressDraft(Announcement $announcement): RedirectResponse
    {
        $context = $this->resolveDeliveryContext($announcement);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['preview' => $preview, 'plan' => $plan, 'entity' => $entity, 'org' => $org, 'project' => $project] = $context;

        if ($plan->action() !== ContentIntelligencePlan::ACTION_CREATE_NEW) {
            return back()->withErrors(['delivery' => WordPressDraftPublisher::ERROR_ACTION_NOT_CREATE_NEW]);
        }

        if ($entity === null) {
            return back()->withErrors(['delivery' => 'entity_not_found']);
        }

        $seo = $plan->seoPlan();
        $slug = is_array($seo) && isset($seo['slug']) ? (string) $seo['slug'] : null;

        $result = $this->wordPressDraftPublisher->createDraft(
            $org->id,
            $project->id,
            $plan->action(),
            $preview->title(),
            $preview->body(),
            $slug,
            $entity,
            request()->user(),
        );

        if (($result['ok'] ?? false) !== true) {
            return back()->withErrors(['delivery' => (string) ($result['reason'] ?? 'wordpress_draft_failed')]);
        }

        return back()->with(
            'status',
            'WordPress draft created (post ID '.($result['remote_post_id'] ?? 'unknown').'). Review in WordPress before Hub release.',
        );
    }

    public function releaseToHub(Request $request, Announcement $announcement): RedirectResponse
    {
        $context = $this->resolveDeliveryContext($announcement);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        ['plan' => $plan, 'entity' => $entity] = $context;

        if ($entity === null) {
            return back()->withErrors(['delivery' => 'entity_not_found']);
        }

        if (! $plan->isResolved()) {
            return back()->withErrors(['delivery' => 'plan_not_resolved']);
        }

        $validated = $request->validate([
            'lifecycle_status' => ['required', 'string', 'max:64'],
            'canonical_url' => ['required', 'url:http,https', 'max:2048'],
            'confirmed' => ['accepted'],
        ]);

        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $result = $this->hubRelease->release(
            $entity,
            $announcement,
            (string) $user->id,
            (string) $validated['lifecycle_status'],
            (string) $validated['canonical_url'],
            true,
        );

        if (($result['ok'] ?? false) !== true) {
            return back()->withErrors(['delivery' => (string) ($result['reason'] ?? 'hub_release_failed')]);
        }

        return back()->with(
            'status',
            'Hub release recorded for entity '.($result['entity_id'] ?? $entity->entity_id).'. Existing Hub rules still apply at read time.',
        );
    }

    /**
     * @return array{
     *     preview: \App\Modules\Editorial\Domain\Article\ArticlePreview,
     *     plan: ContentIntelligencePlan,
     *     entity: ContentEntityModel|null,
     *     org: mixed,
     *     project: mixed
     * }|RedirectResponse
     */
    private function resolveDeliveryContext(Announcement $announcement): array|RedirectResponse
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();

        abort_unless(
            (string) $announcement->organization_id === (string) $org->id
            && (string) $announcement->project_id === (string) $project->id,
            404,
        );

        $preview = $this->previews->findLatestForAnnouncement($org->id, $project->id, $announcement->id);
        if ($preview === null) {
            return back()->withErrors(['delivery' => 'preview_not_available']);
        }

        $plan = $this->planner->planForAnnouncement($org->id, $project->id, $announcement);

        $entity = null;
        $entityId = trim((string) ($plan->entityId() ?? ''));
        if ($entityId !== '') {
            $entity = ContentEntityModel::query()
                ->where('organization_id', $org->id)
                ->where('project_id', $project->id)
                ->where('entity_id', $entityId)
                ->first();
        }

        return compact('preview', 'plan', 'entity', 'org', 'project');
    }
}
