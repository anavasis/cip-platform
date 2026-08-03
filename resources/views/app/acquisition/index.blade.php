@extends('layouts.app')
@section('title', 'Acquisition — CIP')
@section('breadcrumbs') Acquisition @endsection
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
<h1 class="font-[family-name:var(--font-display)] text-3xl font-bold">Acquisition</h1>
<form method="POST" action="{{ route('app.acquisition.run-due') }}">@csrf<button class="cip-btn" type="submit">Run all due</button></form>
</div>
<div class="cip-card mb-4">
<h2 class="mb-2 font-semibold">Pending / running jobs</h2>
<table class="cip-table"><thead><tr><th>Type</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>
@forelse($pendingJobs as $job)
@php $status = is_object($job->status) ? $job->status->value : (string) $job->status; @endphp
<tr>
<td>{{ $job->job_type }}</td><td>{{ $status }}</td><td>{{ $job->created_at }}</td>
<td>@if($status === 'pending')
<form method="POST" action="{{ route('app.acquisition.cancel', $job) }}">@csrf<button class="underline">Cancel</button></form>
@endif</td>
</tr>
@empty<tr><td colspan="4">No pending acquisition jobs.</td></tr>@endforelse
</tbody></table>
</div>
<div class="cip-card">
<h2 class="mb-2 font-semibold">History</h2>
<table class="cip-table"><thead><tr><th>Run</th><th>Status</th><th>Succeeded</th><th>Failed</th><th></th></tr></thead><tbody>
@foreach($runs as $run)
<tr>
<td>{{ $run->run_id ?? $run->id }}</td>
<td>{{ $run->status }}</td>
<td>{{ $run->sources_succeeded }}</td>
<td>{{ $run->sources_failed }}</td>
<td class="space-x-2"><a href="{{ route('app.acquisition.show', $run) }}">Open</a>
<form class="inline" method="POST" action="{{ route('app.acquisition.retry', $run) }}">@csrf<button class="underline">Retry</button></form></td>
</tr>
@endforeach
</tbody></table>
<div class="mt-3">{{ $runs->links() }}</div>
</div>
@endsection
