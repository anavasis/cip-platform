<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\Article\ArticlePreviewRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultRepositoryInterface;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PreviewWebController extends Controller
{
    public function __construct(
        private readonly ArticlePreviewRepositoryInterface $previews,
        private readonly GenerationResultRepositoryInterface $results,
        private readonly GenerateArticlePreviewService $generator,
    ) {}

    public function show(Announcement $announcement): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $preview = $this->previews->findLatestForAnnouncement($org->id, $project->id, $announcement->id);
        abort_if($preview === null, 404, 'Preview not available.');
        $result = $this->results->findById($org->id, $project->id, $preview->resultId());

        return view('app.preview.show', [
            'announcement' => $announcement,
            'preview' => $preview,
            'result' => $result,
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
}
