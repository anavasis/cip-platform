@extends('layouts.guest')
@section('title', 'Initial setup — CIP')
@section('content')
<h1 class="mb-4 text-xl font-semibold">Initial setup wizard</h1>
<form method="POST" action="{{ route('setup.store') }}" class="space-y-3">
@csrf
@if($needsAdmin)
<div><label class="cip-label">Admin name</label><input class="cip-input" name="admin_name" required></div>
<div><label class="cip-label">Admin email</label><input class="cip-input" type="email" name="admin_email" required></div>
<div><label class="cip-label">Admin password</label><input class="cip-input" type="password" name="admin_password" required></div>
@else
<input type="hidden" name="use_current_user" value="1">
<p class="text-sm text-slate-600">Using signed-in user as admin.</p>
@endif
<div><label class="cip-label">Organization name</label><input class="cip-input" name="organization_name" value="CIP Org" required></div>
<div><label class="cip-label">Project name</label><input class="cip-input" name="project_name" value="Default Project" required></div>
<div><label class="cip-label">OpenAI API key (optional)</label><input class="cip-input" type="password" name="openai_api_key"></div>
<div><label class="cip-label">AI model</label><input class="cip-input" name="ai_model" value="gpt-5"></div>
<div><label class="cip-label">Temperature</label><input class="cip-input" type="number" step="0.1" name="ai_temperature" value="0.2"></div>
<div><label class="cip-label">Max tokens</label><input class="cip-input" type="number" name="ai_max_tokens" value="2048"></div>
<div><label class="cip-label">Timeout seconds</label><input class="cip-input" type="number" name="ai_timeout_seconds" value="60"></div>
<div><label class="cip-label">First source name (optional)</label><input class="cip-input" name="source_name"></div>
<div><label class="cip-label">Feed URL</label><input class="cip-input" name="source_feed_url" placeholder="https://example.com/feed.xml"></div>
<div><label class="cip-label">Allowed domain</label><input class="cip-input" name="source_allowed_domain" placeholder="example.com"></div>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enable_editorial" value="1" checked> Enable editorial feature flags</label>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enable_acquisition" value="1" checked> Enable acquisition feature flags</label>
<button class="cip-btn w-full" type="submit">Complete setup</button>
</form>
@endsection
