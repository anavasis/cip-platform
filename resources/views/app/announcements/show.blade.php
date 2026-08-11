@extends('layouts.app')
@section('title', 'Announcement — CIP')
@section('breadcrumbs') <a href="{{ route('app.announcements.index') }}">Announcements</a> / Detail @endsection
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
<h1 class="font-[family-name:var(--font-display)] text-3xl font-bold">{{ $announcement->raw_title }}</h1>
<a class="cip-btn" href="{{ route('app.editorial.show', $announcement) }}">Editorial workspace</a>
</div>
<div class="grid gap-4 lg:grid-cols-2">
<div class="cip-card text-sm space-y-2">
<div><strong>ID:</strong> {{ $announcement->id }}</div>
<div><strong>Status:</strong> <span class="cip-badge">{{ $status }}</span></div>
<div><strong>Source:</strong> {{ $announcement->source_id }}</div>
<div><strong>URL:</strong> <a href="{{ $announcement->canonical_url }}" target="_blank" rel="noopener">{{ $announcement->canonical_url }}</a></div>
<div><strong>Revision:</strong> {{ $announcement->revision_no }}</div>
<div><strong>Identity basis:</strong> {{ $announcement->identity_basis }}</div>
<div><strong>Content hash:</strong> {{ $announcement->content_hash }}</div>
<div><strong>First seen:</strong> {{ $announcement->first_seen_at }}</div>
<div><strong>Last seen:</strong> {{ $announcement->last_seen_at }}</div>
</div>
<div class="cip-card">
<h2 class="mb-2 font-semibold">Timeline</h2>
<ul class="space-y-2 text-sm">
@foreach($timeline as $entry)
<li><strong>{{ $entry['at'] }}</strong> — {{ $entry['label'] }} <span class="text-slate-500">{{ $entry['detail'] }}</span></li>
@endforeach
</ul>
</div>
<div class="cip-card lg:col-span-2">
<h2 class="mb-2 font-semibold">Content Intelligence Plan</h2>
@if($contentIntelligencePlan->isResolved())
<p class="mb-3 text-sm text-slate-600">Deterministic plan preview — no publishing side effects.</p>
<dl class="grid gap-2 text-sm sm:grid-cols-2">
<div><dt class="font-medium text-slate-500">Resolution</dt><dd><span class="cip-badge">{{ $contentIntelligencePlan->status() }}</span> <span class="text-slate-500">({{ $contentIntelligencePlan->confidence() }})</span></dd></div>
<div><dt class="font-medium text-slate-500">Entity</dt><dd>{{ $contentIntelligencePlan->entityLabel() }} <span class="text-slate-500">({{ $contentIntelligencePlan->entityId() }})</span></dd></div>
<div><dt class="font-medium text-slate-500">Role</dt><dd>{{ $contentIntelligencePlan->contentRole() }}</dd></div>
<div><dt class="font-medium text-slate-500">Action</dt><dd><span class="cip-badge">{{ $contentIntelligencePlan->action() }}</span></dd></div>
<div><dt class="font-medium text-slate-500">Canonical target</dt><dd>@if($contentIntelligencePlan->canonicalTargetUrl())<a href="{{ $contentIntelligencePlan->canonicalTargetUrl() }}" target="_blank" rel="noopener">{{ $contentIntelligencePlan->canonicalTargetUrl() }}</a>@else<span class="text-slate-500">—</span>@endif</dd></div>
<div><dt class="font-medium text-slate-500">Hub impact</dt><dd>{{ $contentIntelligencePlan->hubImpact() }}</dd></div>
<div class="sm:col-span-2"><dt class="font-medium text-slate-500">Parent hub</dt><dd>@if($contentIntelligencePlan->parentHubEntityId()){{ $contentIntelligencePlan->parentHubLabel() }} ({{ $contentIntelligencePlan->parentHubEntityId() }}) — <a href="{{ $contentIntelligencePlan->parentHubUrl() }}" target="_blank" rel="noopener">{{ $contentIntelligencePlan->parentHubUrl() }}</a>@else<span class="text-slate-500">—</span>@endif</dd></div>
@if($contentIntelligencePlan->matchedPattern())<div class="sm:col-span-2"><dt class="font-medium text-slate-500">Matched pattern</dt><dd><code>{{ $contentIntelligencePlan->matchedPattern() }}</code></dd></div>@endif
</dl>
@php $seo = $contentIntelligencePlan->seoPlan(); @endphp
@if($seo)
<h3 class="mt-4 mb-2 font-semibold text-sm">SEO plan</h3>
<dl class="grid gap-2 text-sm sm:grid-cols-2">
<div class="sm:col-span-2"><dt class="font-medium text-slate-500">Search intent</dt><dd>{{ $seo['search_intent'] ?? '—' }}</dd></div>
<div><dt class="font-medium text-slate-500">SEO title</dt><dd>{{ $seo['seo_title'] ?? '—' }}</dd></div>
<div><dt class="font-medium text-slate-500">H1</dt><dd>{{ $seo['h1'] ?? '—' }}</dd></div>
<div><dt class="font-medium text-slate-500">Slug</dt><dd>{{ $seo['slug'] ?? '—' }}</dd></div>
<div class="sm:col-span-2"><dt class="font-medium text-slate-500">Meta description</dt><dd>{{ $seo['meta_description'] ?? '—' }}</dd></div>
</dl>
@endif
@if(count($contentIntelligencePlan->internalLinks()) > 0)
<h3 class="mt-4 mb-2 font-semibold text-sm">Internal links</h3>
<ul class="space-y-2 text-sm">
@foreach($contentIntelligencePlan->internalLinks() as $link)
<li><strong>{{ $link['type'] }}</strong> → <a href="{{ $link['target_url'] }}" target="_blank" rel="noopener">{{ $link['target_url'] }}</a> ({{ $link['target_entity_id'] }}, anchor: {{ $link['anchor_text'] }})</li>
@endforeach
</ul>
@endif
@if(count($contentIntelligencePlan->publishingOperations()) > 0)
<h3 class="mt-4 mb-2 font-semibold text-sm">Publishing operations</h3>
<ul class="space-y-2 text-sm">
@foreach($contentIntelligencePlan->publishingOperations() as $operation)
<li><strong>{{ $operation['operation'] }}</strong> — {{ $operation['entity_id'] }} @if(!empty($operation['target_url']))→ {{ $operation['target_url'] }}@endif <span class="cip-badge">{{ $operation['mode'] }}</span></li>
@endforeach
</ul>
@endif
@else
<p class="text-sm text-slate-600">No entity resolved for this announcement. Configure a Content Intelligence profile in Settings or adjust entity rules.</p>
<p class="mt-2 text-sm"><strong>Status:</strong> <span class="cip-badge">{{ $contentIntelligencePlan->status() }}</span> · <strong>Action:</strong> <span class="cip-badge">{{ $contentIntelligencePlan->action() }}</span></p>
@endif
@if(count($contentIntelligencePlan->warnings()) > 0)
<h3 class="mt-4 mb-2 font-semibold text-sm text-amber-700">Warnings</h3>
<ul class="list-disc pl-5 text-sm text-amber-800 space-y-1">
@foreach($contentIntelligencePlan->warnings() as $warning)
<li>{{ $warning }}</li>
@endforeach
</ul>
@endif
</div>
<div class="cip-card lg:col-span-2">
<h2 class="mb-2 font-semibold">Metadata / payload</h2>
<pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs">{{ json_encode($announcement->raw_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
</div>
<div class="cip-card lg:col-span-2">
<h2 class="mb-2 font-semibold">Revision history</h2>
<p class="mb-2 text-sm text-slate-600">Current revision {{ $announcement->revision_no }} with content hash {{ $announcement->content_hash }}.</p>
<table class="cip-table"><thead><tr><th>Generation</th><th>Status</th><th>Provider</th><th>When</th></tr></thead><tbody>
@forelse($generations as $generation)
<tr>
<td>{{ $generation->result_id }}</td>
<td>{{ $generation->status }}</td>
<td>{{ $generation->provider_code }}</td>
<td>{{ $generation->created_at }}</td>
</tr>
@empty
<tr><td colspan="4">No generation history yet.</td></tr>
@endforelse
</tbody></table>
</div>
</div>
@endsection
