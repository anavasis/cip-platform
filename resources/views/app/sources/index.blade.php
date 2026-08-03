@extends('layouts.app')
@section('title', 'Sources — CIP')
@section('breadcrumbs') Sources @endsection
@section('content')
<div class="mb-4 flex items-center justify-between gap-3">
    <h1 class="font-[family-name:var(--font-display)] text-3xl font-bold">Sources</h1>
    <a class="cip-btn" href="{{ route('app.sources.create') }}">New source</a>
</div>
<p class="mb-4 text-sm text-slate-600">
    Disable is the normal safe action for sources that should stop acquiring.
    Delete is allowed only for unused sources with no announcements, acquisition history, or editorial artifacts.
</p>
<div class="cip-card overflow-x-auto">
<table class="cip-table">
<thead><tr><th>Name</th><th>Type</th><th>Enabled</th><th>Manual</th><th>Check</th><th>Interval</th><th></th></tr></thead>
<tbody>
@forelse($sources as $source)
@php $row = is_array($source) ? $source : $source; @endphp
<tr>
    <td>{{ is_array($row) ? ($row['name'] ?? '') : $row->name }}</td>
    <td>{{ is_array($row) ? ($row['source_type'] ?? '') : $row->source_type }}</td>
    <td>{{ (is_array($row) ? ($row['enabled'] ?? false) : $row->enabled) ? 'yes' : 'no' }}</td>
    <td>{{ (is_array($row) ? ($row['manual_only'] ?? false) : $row->manual_only) ? 'yes' : 'no' }}</td>
    <td>
        @php $status = is_array($row) ? ($row['last_check_status'] ?? null) : $row->last_check_status; @endphp
        <span class="cip-badge">{{ $status ?: 'unchecked' }}</span>
    </td>
    <td>{{ is_array($row) ? ($row['acquire_interval_seconds'] ?? '') : $row->acquire_interval_seconds }}s</td>
    <td class="space-x-2 whitespace-nowrap">
        @php $id = is_array($row) ? ($row['id'] ?? '') : $row->id; @endphp
        <a href="{{ route('app.sources.edit', $id) }}">Edit</a>
        <form class="inline" method="POST" action="{{ route('app.sources.run', $id) }}">@csrf<button class="underline">Run</button></form>
        <form class="inline" method="POST" action="{{ route('app.sources.check', $id) }}">@csrf<button class="underline">Test</button></form>
        <form class="inline" method="POST" action="{{ route((is_array($row) ? ($row['enabled'] ?? false) : $row->enabled) ? 'app.sources.disable' : 'app.sources.enable', $id) }}">@csrf<button class="underline">{{ (is_array($row) ? ($row['enabled'] ?? false) : $row->enabled) ? 'Disable' : 'Enable' }}</button></form>
        <form class="inline" method="POST" action="{{ route('app.sources.destroy', $id) }}" onsubmit="return confirm('Delete only unused sources. Prefer Disable if this source has history. Continue?')">@csrf @method('DELETE')<button class="underline text-red-700">Delete</button></form>
    </td>
</tr>
@empty
<tr><td colspan="7">No sources yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
@endsection
