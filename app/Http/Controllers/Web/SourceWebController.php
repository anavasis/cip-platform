<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\JobEngineService;
use App\Http\Controllers\Controller;
use App\Modules\Acquisition\Application\SourceRegistryService;
use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use App\Modules\Acquisition\Infrastructure\Jobs\SourceConnectivityCheckJob;
use App\Modules\Announcement\Application\EditorialIngestionService;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRunItem;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SourceWebController extends Controller
{
    public function __construct(
        private readonly SourceRepositoryInterface $sources,
        private readonly SourceRegistryService $registry,
        private readonly JobEngineService $jobs,
    ) {}

    public function index(): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();

        return view('app.sources.index', [
            'sources' => $this->sources->findAll($org->id, $project->id),
        ]);
    }

    public function create(): View
    {
        return view('app.sources.form', ['source' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $validated = $request->validate($this->rules(false));
        $validated['allowed_domains'] = $this->domains($request);
        $validated['enabled'] = $request->boolean('enabled', true);
        $validated['manual_only'] = $request->boolean('manual_only', false);
        $result = $this->registry->create($org->id, $project->id, $validated);
        if (($result['success'] ?? false) !== true) {
            return back()->withInput()->withErrors(['form' => (string) ($result['error'] ?? 'source_create_failed')]);
        }

        return redirect()->route('app.sources.index')->with('status', 'Source created.');
    }

    public function edit(Source $source): View
    {
        $this->assertTenant($source);

        return view('app.sources.form', ['source' => $source]);
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $this->assertTenant($source);
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $validated = $request->validate($this->rules(true));
        $validated['allowed_domains'] = $this->domains($request);
        $validated['manual_only'] = $request->boolean('manual_only', (bool) $source->manual_only);
        if ($request->has('enabled')) {
            $validated['enabled'] = $request->boolean('enabled');
        }
        $input = array_merge([
            'name' => $source->name,
            'source_type' => $source->source_type,
            'base_url' => $source->base_url,
            'feed_url' => $source->feed_url,
            'allowed_domains' => $source->allowed_domains ?? [],
            'parser_profile' => $source->parser_profile,
            'manual_only' => $source->manual_only,
            'acquire_interval_seconds' => $source->acquire_interval_seconds,
            'enabled' => $source->enabled,
        ], $validated);
        $result = $this->registry->update($org->id, $project->id, (string) $source->id, $input);
        if (($result['success'] ?? false) !== true) {
            return back()->withInput()->withErrors(['form' => (string) ($result['error'] ?? 'source_update_failed')]);
        }

        return redirect()->route('app.sources.index')->with('status', 'Source updated.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        $this->assertTenant($source);

        if ($this->hasOperationalDependencies($source)) {
            return back()->withErrors([
                'form' => 'This source cannot be deleted because it has operational history (announcements, acquisition runs, or editorial artifacts). Disable the source instead.',
            ]);
        }

        $source->delete();

        return redirect()->route('app.sources.index')->with('status', 'Source deleted.');
    }

    private function hasOperationalDependencies(Source $source): bool
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $orgId = (string) $org->id;
        $projectId = (string) $project->id;
        $sourceId = (string) $source->id;

        if (Announcement::query()
            ->where('organization_id', $orgId)
            ->where('project_id', $projectId)
            ->where('source_id', $sourceId)
            ->exists()) {
            return true;
        }

        if (AcquisitionRunItem::query()
            ->where('organization_id', $orgId)
            ->where('project_id', $projectId)
            ->where('source_id', $sourceId)
            ->exists()) {
            return true;
        }

        // Editorial artifacts are reachable through announcements in this tenant.
        $announcementIds = Announcement::query()
            ->where('organization_id', $orgId)
            ->where('project_id', $projectId)
            ->where('source_id', $sourceId)
            ->pluck('id');
        if ($announcementIds->isNotEmpty()
            && GenerationResultModel::query()
                ->where('organization_id', $orgId)
                ->where('project_id', $projectId)
                ->whereIn('announcement_id', $announcementIds)
                ->exists()) {
            return true;
        }

        return false;
    }

    public function enable(Source $source): RedirectResponse
    {
        $this->assertTenant($source);

        return $this->toggle($source, true);
    }

    public function disable(Source $source): RedirectResponse
    {
        $this->assertTenant($source);

        return $this->toggle($source, false);
    }

    public function check(Source $source): RedirectResponse
    {
        $this->assertTenant($source);
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $platformJob = $this->jobs->create('acquisition.source_connectivity_check', $org->id, $project->id, [
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
        ]);
        SourceConnectivityCheckJob::dispatch($platformJob->id);

        return back()->with('status', 'Connectivity check queued.');
    }

    public function run(Source $source): RedirectResponse
    {
        $this->assertTenant($source);
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $platformJob = $this->jobs->create('acquisition.acquire_source', $org->id, $project->id, [
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
        ]);
        AcquireSourceJob::dispatch($platformJob->id);

        return back()->with('status', 'Acquisition run queued.');
    }

    public function runAndIngest(Source $source, EditorialIngestionService $ingestion): RedirectResponse
    {
        $this->assertTenant($source);
        $org = OperatorContext::organization();
        $project = OperatorContext::project();

        $result = $ingestion
            ->forTenant($org->id, $project->id)
            ->ingestFromSource((string) $source->id);

        if ($result->success() !== true) {
            return back()->withErrors([
                'ingest' => $result->errorCode() !== ''
                    ? $result->errorCode()
                    : 'ingestion_failed',
            ]);
        }

        return back()->with(
            'status',
            sprintf(
                'Run + Ingest complete: %d new, %d updated, %d unchanged, %d duplicate.',
                $result->newCount(),
                $result->updatedCount(),
                $result->unchangedCount(),
                $result->duplicateCount(),
            ),
        );
    }

    private function toggle(Source $source, bool $enabled): RedirectResponse
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $result = $this->registry->toggle($org->id, $project->id, (string) $source->id, $enabled);
        if (($result['success'] ?? false) !== true) {
            return back()->withErrors(['form' => (string) ($result['error'] ?? 'source_toggle_failed')]);
        }

        return back()->with('status', $enabled ? 'Source enabled.' : 'Source disabled.');
    }

    private function assertTenant(Source $source): void
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        abort_unless(
            (string) $source->organization_id === (string) $org->id
            && (string) $source->project_id === (string) $project->id,
            404
        );
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'slug' => [$presence, 'string', 'max:128'],
            'name' => [$presence, 'string', 'max:191'],
            'source_type' => [$presence, 'string', 'in:rss,atom,html,json,xml,pdf,manual'],
            'base_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'feed_url' => [$presence, 'url:http,https', 'max:2048'],
            'allowed_domains_text' => [$presence, 'string'],
            'parser_profile' => ['sometimes', 'nullable', 'string', 'max:64'],
            'manual_only' => ['sometimes', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
            'acquire_interval_seconds' => ['sometimes', 'integer', 'min:1', 'max:31536000'],
        ];
    }

    /** @return list<string> */
    private function domains(Request $request): array
    {
        $raw = (string) $request->input('allowed_domains_text', '');
        $parts = preg_split('/[\s,]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
