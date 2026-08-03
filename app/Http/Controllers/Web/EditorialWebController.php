<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationRequestModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EditorialWebController extends Controller
{
    public function __construct(
        private readonly GenerateArticlePreviewService $generator,
    ) {}

    public function show(Announcement $announcement): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();

        $history = GenerationRequestModel::query()
            ->where('organization_id', $org->id)
            ->where('project_id', $project->id)
            ->where('announcement_id', $announcement->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $latestResult = GenerationResultModel::query()
            ->where('organization_id', $org->id)
            ->where('project_id', $project->id)
            ->where('announcement_id', $announcement->id)
            ->orderByDesc('created_at')
            ->first();

        return view('app.editorial.show', [
            'announcement' => $announcement,
            'history' => $history,
            'latestResult' => $latestResult,
            'latest' => [
                'result' => $latestResult,
                'request' => $history->first(),
            ],
        ]);
    }

    public function generate(Announcement $announcement): RedirectResponse
    {
        return $this->run($announcement, false);
    }

    public function regenerate(Announcement $announcement): RedirectResponse
    {
        return $this->run($announcement, true);
    }

    private function run(Announcement $announcement, bool $regenerate): RedirectResponse
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $user = request()->user();

        try {
            $out = $this->generator->generate(
                $org->id,
                $project->id,
                $announcement->id,
                $user?->id,
                null,
                $regenerate,
                false,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['generation' => $e->getMessage()]);
        }

        if (($out['ok'] ?? false) === true) {
            return redirect()
                ->route('app.preview.show', $announcement)
                ->with('status', ($out['reused'] ?? false) ? 'Reused existing preview.' : 'Article generated.');
        }

        return back()->withErrors([
            'generation' => (string) ($out['error_code'] ?? $out['error'] ?? 'generation_failed'),
        ]);
    }
}
