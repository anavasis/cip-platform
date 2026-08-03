@extends('layouts.app')
@section('title', 'Job — CIP')
@section('content')
<h1 class="mb-4 font-[family-name:var(--font-display)] text-3xl font-bold">Platform job</h1>
<div class="cip-card space-y-2 text-sm">
<div>ID: {{ $job->id }}</div>
<div>Type: {{ $job->job_type }}</div>
<div>Status: {{ is_object($job->status) ? $job->status->value : $job->status }}</div>
<div>Error: {{ $job->error }}</div>
<div>Started: {{ $job->started_at }}</div>
<div>Completed: {{ $job->completed_at }}</div>
<h2 class="pt-3 font-semibold">Payload</h2>
<pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs">{{ json_encode($job->payload, JSON_PRETTY_PRINT) }}</pre>
<h2 class="pt-3 font-semibold">Result</h2>
<pre class="overflow-x-auto rounded bg-slate-50 p-3 text-xs">{{ json_encode($job->result, JSON_PRETTY_PRINT) }}</pre>
</div>
@endsection
