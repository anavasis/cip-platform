<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use App\Support\OperatorContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementWebController extends Controller
{
    public function index(Request $request): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $query = Announcement::query()
            ->where('organization_id', $org->id)
            ->where('project_id', $project->id)
            ->orderByDesc('last_seen_at');

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('raw_title', 'like', $term)->orWhere('canonical_url', 'like', $term);
            });
        }
        if ($request->filled('source_id')) {
            $query->where('source_id', $request->string('source_id'));
        }
        if ($request->filled('revision_no')) {
            $query->where('revision_no', (int) $request->input('revision_no'));
        }
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'revised') {
                $query->where('revision_no', '>', 1);
            } elseif ($status === 'new') {
                $query->where('revision_no', 1);
            }
        }

        return view('app.announcements.index', [
            'announcements' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['search', 'source_id', 'status', 'revision_no']),
        ]);
    }

    public function show(Announcement $announcement): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $generations = GenerationResultModel::query()
            ->where('organization_id', $org->id)
            ->where('project_id', $project->id)
            ->where('announcement_id', $announcement->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $timeline = [
            ['at' => $announcement->first_seen_at, 'label' => 'First seen', 'detail' => 'Revision 1'],
            ['at' => $announcement->last_seen_at, 'label' => 'Last seen', 'detail' => 'Revision '.$announcement->revision_no],
        ];
        foreach ($generations as $generation) {
            $timeline[] = [
                'at' => $generation->created_at,
                'label' => 'Generation '.$generation->status,
                'detail' => $generation->result_id.($generation->error_code ? ' · '.$generation->error_code : ''),
            ];
        }
        usort($timeline, static fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));

        return view('app.announcements.show', [
            'announcement' => $announcement,
            'timeline' => $timeline,
            'generations' => $generations,
            'status' => ((int) $announcement->revision_no) > 1 ? 'revised' : 'new',
        ]);
    }
}
