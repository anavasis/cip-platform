@extends('layouts.app')
@section('title', 'Editorial — CIP')
@section('breadcrumbs') <a href="{{ route('app.announcements.show', $announcement) }}">Announcement</a> / Editorial @endsection
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
<div>
<h1 class="font-[family-name:var(--font-display)] text-3xl font-bold">Editorial workspace</h1>
<p class="text-sm text-slate-600">{{ $announcement->raw_title }}</p>
</div>
<div class="flex gap-2">
<form method="POST" action="{{ route('app.editorial.generate', $announcement) }}">@csrf<button class="cip-btn" type="submit">Generate</button></form>
<form method="POST" action="{{ route('app.editorial.regenerate', $announcement) }}">@csrf<button class="cip-btn-secondary" type="submit">Regenerate</button></form>
@if($latestResult && $latestResult->status === 'success')
<a class="cip-btn-secondary" href="{{ route('app.preview.show', $announcement) }}">Open preview</a>
@endif
</div>
</div>
<div class="grid gap-4 lg:grid-cols-2">
<div class="cip-card text-sm space-y-2">
<h2 class="font-semibold">Latest result</h2>
@if($latestResult)
<div>Status: <span class="cip-badge">{{ $latestResult->status }}</span></div>
<div>Result ID: {{ $latestResult->result_id }}</div>
<div>Request ID: {{ $latestResult->request_id }}</div>
<div>Provider: {{ $latestResult->provider_code }}</div>
<div>Error: {{ $latestResult->error_code ?: 'none' }}</div>
<div>Duration: {{ $latestResult->duration_ms }} ms</div>
<div>Preview available: {{ $latestResult->status === 'success' ? 'yes' : 'no' }}</div>
<pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs">{{ json_encode($latestResult->payload ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
@else
<p>No generation result yet.</p>
@endif
</div>
<div class="cip-card text-sm space-y-2">
<h2 class="font-semibold">Latest request metadata</h2>
@if($latest['request'] ?? null)
<div>Request ID: {{ $latest['request']->request_id }}</div>
<div>Status: {{ $latest['request']->status }}</div>
<div>Created: {{ $latest['request']->created_at }}</div>
<pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs">{{ json_encode($latest['request']->payload ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
@else
<p>No request metadata yet.</p>
@endif
</div>
<div class="cip-card lg:col-span-2">
<h2 class="mb-2 font-semibold">Generation history</h2>
<table class="cip-table"><thead><tr><th>Request</th><th>Status</th><th>Created</th></tr></thead><tbody>
@forelse($history as $req)
<tr><td>{{ $req->request_id }}</td><td>{{ $req->status }}</td><td>{{ $req->created_at }}</td></tr>
@empty<tr><td colspan="3">No requests yet.</td></tr>@endforelse
</tbody></table>
</div>
</div>
@endsection
