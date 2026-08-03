@extends('layouts.app')
@section('title', 'Acquisition run — CIP')
@section('breadcrumbs') <a href="{{ route('app.acquisition.index') }}">Acquisition</a> / Run @endsection
@section('content')
<h1 class="mb-4 font-[family-name:var(--font-display)] text-3xl font-bold">Acquisition run</h1>
<div class="cip-card mb-4 text-sm space-y-1">
<div>ID: {{ $run->id }}</div>
<div>Run ID: {{ $run->run_id ?? 'n/a' }}</div>
<div>Status: {{ $run->status }}</div>
<div>Error: {{ $run->error_code ?: 'none' }}</div>
<div>Duration: {{ $run->duration_ms }} ms</div>
<div>Progress: {{ (int) $run->sources_succeeded + (int) $run->sources_failed }} items · {{ $run->sources_succeeded }} succeeded · {{ $run->sources_failed }} failed</div>
</div>
<div class="cip-card">
<h2 class="mb-2 font-semibold">Run items / logs</h2>
<table class="cip-table"><thead><tr><th>Source</th><th>Success</th><th>Error</th><th>Meta</th></tr></thead><tbody>
@forelse($items as $item)
<tr>
<td>{{ $item->source_id }}</td>
<td>{{ $item->success ? 'yes' : 'no' }}</td>
<td>{{ $item->error_code ?: 'none' }}</td>
<td><pre class="max-w-xl overflow-x-auto text-xs">{{ json_encode($item->result_meta ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></td>
</tr>
@empty<tr><td colspan="4">No run items.</td></tr>@endforelse
</tbody></table>
</div>
@endsection
