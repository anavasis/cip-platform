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
