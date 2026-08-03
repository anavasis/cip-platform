@extends('layouts.app')
@section('title', ($source ? 'Edit' : 'Create').' source — CIP')
@section('breadcrumbs') <a href="{{ route('app.sources.index') }}">Sources</a> / {{ $source ? 'Edit' : 'Create' }} @endsection
@section('content')
<h1 class="mb-4 font-[family-name:var(--font-display)] text-3xl font-bold">{{ $source ? 'Edit source' : 'Create source' }}</h1>
<form class="cip-card max-w-2xl space-y-4" method="POST" action="{{ $source ? route('app.sources.update', $source) : route('app.sources.store') }}">
@csrf
@if($source) @method('PUT') @endif
@php
$domains = old('allowed_domains_text', $source ? implode(', ', (array) ($source->allowed_domains ?? [])) : '');
@endphp
<div><label class="cip-label">Slug</label><input class="cip-input" name="slug" value="{{ old('slug', $source->slug ?? '') }}" required></div>
<div><label class="cip-label">Name</label><input class="cip-input" name="name" value="{{ old('name', $source->name ?? '') }}" required></div>
<div><label class="cip-label">Type</label>
<select class="cip-input" name="source_type" required>
@foreach(['rss','atom','html','json','xml','pdf','manual'] as $t)
<option value="{{ $t }}" @selected(old('source_type', $source->source_type ?? 'rss')===$t)>{{ $t }}</option>
@endforeach
</select></div>
<div><label class="cip-label">Base URL</label><input class="cip-input" name="base_url" value="{{ old('base_url', $source->base_url ?? '') }}"></div>
<div><label class="cip-label">Feed URL</label><input class="cip-input" name="feed_url" value="{{ old('feed_url', $source->feed_url ?? '') }}" required></div>
<div><label class="cip-label">Allowed domains (comma-separated)</label><input class="cip-input" name="allowed_domains_text" value="{{ $domains }}" required></div>
<div><label class="cip-label">Parser profile</label><input class="cip-input" name="parser_profile" value="{{ old('parser_profile', $source->parser_profile ?? '') }}"></div>
<div><label class="cip-label">Acquire interval (seconds)</label><input class="cip-input" type="number" name="acquire_interval_seconds" value="{{ old('acquire_interval_seconds', $source->acquire_interval_seconds ?? 3600) }}"></div>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $source->enabled ?? true))> Enabled</label>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="manual_only" value="1" @checked(old('manual_only', $source->manual_only ?? false))> Manual only</label>
<div class="rounded-md border border-[var(--color-line)] bg-slate-50 p-3 text-sm text-slate-700">
    <strong>Credentials:</strong> embedded URL credentials are rejected by the acquisition safety layer. Use public/allowlisted feeds only.
    <div class="mt-1"><strong>Capabilities:</strong> source type maps to the registered safe_feed collector/parser profiles.</div>
    @if($source)
        <div class="mt-1"><strong>Last check status:</strong> {{ $source->last_check_status ?: 'never checked' }}</div>
        <div><strong>Last checked at:</strong> {{ $source->last_checked_at ?: 'n/a' }}</div>
    @endif
</div>
<button class="cip-btn" type="submit">Save source</button>
</form>
@endsection
