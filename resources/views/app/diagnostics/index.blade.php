@extends('layouts.app')
@section('title', 'Diagnostics — CIP')
@section('content')
<h1 class="mb-6 font-[family-name:var(--font-display)] text-3xl font-bold">Diagnostics</h1>
<div class="cip-card mb-4">
<h2 class="mb-2 font-semibold">Health checks</h2>
<ul class="text-sm space-y-1">
@foreach($health['checks'] as $name => $check)
<li><strong>{{ $name }}</strong>: {{ $check['status'] }} — {{ $check['message'] ?? '' }}</li>
@endforeach
</ul>
<pre class="mt-3 overflow-x-auto rounded bg-slate-50 p-3 text-xs">{{ json_encode($editorial, JSON_PRETTY_PRINT) }}</pre>
</div>
<div class="grid gap-4 lg:grid-cols-2">
<div class="cip-card"><h2 class="mb-2 font-semibold">Generation failures</h2>
<table class="cip-table"><thead><tr><th>Result</th><th>Error</th></tr></thead><tbody>
@foreach($generationFailures as $f)<tr><td>{{ $f->result_id }}</td><td>{{ $f->error_code }}</td></tr>@endforeach
</tbody></table></div>
<div class="cip-card"><h2 class="mb-2 font-semibold">Acquisition failures</h2>
<table class="cip-table"><thead><tr><th>Run</th><th>Status</th><th>Error</th></tr></thead><tbody>
@foreach($acquisitionFailures as $f)<tr><td>{{ $f->id }}</td><td>{{ $f->status }}</td><td>{{ $f->error_code }}</td></tr>@endforeach
</tbody></table></div>
<div class="cip-card"><h2 class="mb-2 font-semibold">Queue failures</h2>
<table class="cip-table"><thead><tr><th>Job</th><th>Error</th></tr></thead><tbody>
@foreach($queueFailures as $f)<tr><td>{{ $f->job_type }}</td><td>{{ $f->error }}</td></tr>@endforeach
</tbody></table></div>
<div class="cip-card"><h2 class="mb-2 font-semibold">Failed events</h2>
<table class="cip-table"><thead><tr><th>Type</th><th>When</th></tr></thead><tbody>
@foreach($recentFailedEvents as $f)<tr><td>{{ $f->event_type }}</td><td>{{ $f->occurred_at }}</td></tr>@endforeach
</tbody></table></div>
</div>
@endsection
