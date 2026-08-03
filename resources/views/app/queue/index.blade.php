@extends('layouts.app')
@section('title', 'Queue — CIP')
@section('content')
<h1 class="mb-4 font-[family-name:var(--font-display)] text-3xl font-bold">Queue</h1>
<div class="mb-4 flex flex-wrap gap-2 text-sm">
@foreach($counts as $k=>$v)
<a class="cip-badge" href="{{ route('app.queue.index', ['status'=>$k]) }}">{{ $k }}: {{ $v }}</a>
@endforeach
</div>
<div class="cip-card overflow-x-auto">
<table class="cip-table"><thead><tr><th>Type</th><th>Status</th><th>Error</th><th>Created</th><th></th></tr></thead><tbody>
@foreach($jobs as $job)
<tr>
<td>{{ $job->job_type }}</td>
<td>{{ is_object($job->status) ? $job->status->value : $job->status }}</td>
<td class="max-w-xs truncate">{{ $job->error }}</td>
<td>{{ $job->created_at }}</td>
<td class="space-x-2 whitespace-nowrap">
<a href="{{ route('app.queue.show', $job) }}">Open</a>
<form class="inline" method="POST" action="{{ route('app.queue.retry', $job) }}">@csrf<button class="underline">Retry</button></form>
@if((is_object($job->status) ? $job->status->value : $job->status) === 'pending')
<form class="inline" method="POST" action="{{ route('app.queue.cancel', $job) }}">@csrf<button class="underline">Cancel</button></form>
@endif
</td>
</tr>
@endforeach
</tbody></table>
<div class="mt-3">{{ $jobs->links() }}</div>
</div>
@endsection
