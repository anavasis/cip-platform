@extends('layouts.app')
@section('title', 'Settings — CIP')
@section('breadcrumbs') Settings @endsection
@section('content')
<h1 class="mb-6 font-[family-name:var(--font-display)] text-3xl font-bold">Settings</h1>
<div class="grid gap-4 lg:grid-cols-2">
<form class="cip-card space-y-3" method="POST" action="{{ route('app.settings.ai') }}">
@csrf
<h2 class="text-lg font-semibold">AI provider</h2>
<p class="text-sm text-slate-600">Provider: <span class="cip-badge">OpenAI</span> · Driver: <span class="cip-badge">{{ $ai['driver'] }}</span></p>
<p class="text-sm text-slate-600">Secrets status: {{ $secretConfigured ? 'openai_api_key configured' : 'openai_api_key missing' }}</p>
<div><label class="cip-label">Model</label><input class="cip-input" name="model" value="{{ $ai['model'] }}" required></div>
<div><label class="cip-label">Temperature</label><input class="cip-input" type="number" step="0.1" name="temperature" value="{{ $ai['temperature'] }}" required></div>
<div><label class="cip-label">Max tokens</label><input class="cip-input" type="number" name="max_tokens" value="{{ $ai['max_tokens'] }}" required></div>
<div><label class="cip-label">Timeout (seconds)</label><input class="cip-input" type="number" name="timeout_seconds" value="{{ $ai['timeout_seconds'] }}" required></div>
<p class="text-sm text-slate-600">Editorial instructions are project-specific. Different projects can use different language, tone and article rules.</p>
<div><label class="cip-label">System prompt</label><textarea class="cip-input min-h-32" name="system_prompt" rows="6">{{ $ai['system_prompt'] }}</textarea></div>
<div><label class="cip-label">Article instructions</label><textarea class="cip-input min-h-32" name="article_instructions" rows="8">{{ $ai['article_instructions'] }}</textarea></div>
<div><label class="cip-label">OpenAI API key (leave blank to keep)</label><input class="cip-input" type="password" name="api_key" autocomplete="new-password"></div>
<button class="cip-btn" type="submit">Save AI settings</button>
</form>
<form class="cip-card space-y-3" method="POST" action="{{ route('app.settings.flags') }}">
@csrf
<h2 class="text-lg font-semibold">Feature flags</h2>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="editorial" value="1" @checked($flags['editorial'] ?? false)> editorial</label>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="editorial_generation" value="1" @checked($flags['editorial_generation'] ?? false)> editorial_generation</label>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="acquisition" value="1" @checked($flags['acquisition'] ?? false)> acquisition</label>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="source_registry" value="1" @checked($flags['source_registry'] ?? false)> source_registry</label>
<button class="cip-btn" type="submit">Save flags</button>
<div class="pt-4 text-sm text-slate-600 space-y-1">
<div>Queue connection: {{ $queueConnection }}</div>
<div>Scheduler status: {{ $scheduler }}</div>
<div>Health: {{ $health['status'] }}</div>
<ul class="mt-2 space-y-1">
@foreach($health['checks'] as $name => $check)
<li>{{ $name }}: {{ $check['status'] }}</li>
@endforeach
</ul>
</div>
</form>
</div>
@endsection
