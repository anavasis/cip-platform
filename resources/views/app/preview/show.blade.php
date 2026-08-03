@extends('layouts.app')
@section('title', 'Article preview — CIP')
@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
<h1 class="font-[family-name:var(--font-display)] text-3xl font-bold">Article preview</h1>
<div class="flex flex-wrap gap-2">
<button class="cip-btn-secondary" type="button" onclick="copyText('title')">Copy title</button>
<button class="cip-btn-secondary" type="button" onclick="copyText('body')">Copy body</button>
<button class="cip-btn-secondary" type="button" onclick="copyText('markdown')">Copy markdown</button>
<button class="cip-btn-secondary" type="button" onclick="copyText('full')">Copy full article</button>
<a class="cip-btn-secondary" href="{{ route('app.preview.download', $announcement) }}">Download markdown</a>
<form method="POST" action="{{ route('app.preview.regenerate', $announcement) }}">@csrf<button class="cip-btn" type="submit">Regenerate</button></form>
</div>
</div>
<div class="grid gap-4 lg:grid-cols-[1fr_280px]">
<article class="cip-card space-y-4">
<h2 id="preview-title" class="font-[family-name:var(--font-display)] text-2xl font-bold">{{ $preview->title() }}</h2>
<div id="preview-body" class="prose max-w-none whitespace-pre-wrap text-sm leading-7">{{ $preview->body() }}</div>
</article>
<aside class="cip-card space-y-2 text-sm">
<div><strong>Preview ID:</strong> {{ $preview->previewId() }}</div>
<div><strong>Result ID:</strong> {{ $preview->resultId() }}</div>
<div><strong>Request ID:</strong> {{ $preview->requestId() }}</div>
@php
    $providerCode = $result?->providerExecution()?->providerCode() ?? 'n/a';
    $modelId = $result?->toArray()['model_id'] ?? 'configured-provider-model';
    $duration = $result?->durationMs() ?? 'n/a';
@endphp
<div><strong>Provider:</strong> {{ $providerCode }}</div>
<div><strong>Model:</strong> {{ $modelId }}</div>
<div><strong>Generated at:</strong> {{ $preview->createdAtUtc() }}</div>
<div><strong>Duration:</strong> {{ $duration }} ms</div>
</aside>
</div>
<textarea id="markdown-source" class="hidden"># {{ $preview->title() }}

{{ $preview->body() }}</textarea>
<textarea id="full-source" class="hidden">Title: {{ $preview->title() }}

{{ $preview->body() }}</textarea>
@endsection
@push('scripts')
<script>
function copyText(kind) {
  let value = '';
  if (kind === 'title') value = document.getElementById('preview-title').innerText;
  if (kind === 'body') value = document.getElementById('preview-body').innerText;
  if (kind === 'markdown') value = document.getElementById('markdown-source').value;
  if (kind === 'full') value = document.getElementById('full-source').value;
  navigator.clipboard.writeText(value);
}
</script>
@endpush
