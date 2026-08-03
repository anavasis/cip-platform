@extends('layouts.app')
@section('title', 'Announcements — CIP')
@section('breadcrumbs') Announcements @endsection
@section('content')
<h1 class="mb-4 font-[family-name:var(--font-display)] text-3xl font-bold">Announcements</h1>
<form class="cip-card mb-4 grid gap-3 md:grid-cols-4" method="GET">
<div><label class="cip-label">Search</label><input class="cip-input" name="search" value="{{ $filters['search'] ?? '' }}"></div>
<div><label class="cip-label">Source ID</label><input class="cip-input" name="source_id" value="{{ $filters['source_id'] ?? '' }}"></div>
<div><label class="cip-label">Status</label>
<select class="cip-input" name="status">
<option value="">All</option>
<option value="new" @selected(($filters['status'] ?? '')==='new')>New</option>
<option value="revised" @selected(($filters['status'] ?? '')==='revised')>Revised</option>
</select></div>
<div class="flex items-end"><button class="cip-btn" type="submit">Filter</button></div>
</form>
<div class="cip-card overflow-x-auto">
<table class="cip-table">
<thead><tr><th>Title</th><th>Status</th><th>Revision</th><th>Last seen</th><th></th></tr></thead>
<tbody>
@forelse($announcements as $announcement)
<tr>
<td>{{ $announcement->raw_title }}</td>
<td><span class="cip-badge">{{ ((int)$announcement->revision_no) > 1 ? 'revised' : 'new' }}</span></td>
<td>{{ $announcement->revision_no }}</td>
<td>{{ $announcement->last_seen_at }}</td>
<td class="space-x-2">
<a href="{{ route('app.announcements.show', $announcement) }}">Open</a>
<a href="{{ route('app.editorial.show', $announcement) }}">Editorial</a>
</td>
</tr>
@empty
<tr><td colspan="5">No announcements in this project.</td></tr>
@endforelse
</tbody>
</table>
<div class="mt-3">{{ $announcements->links() }}</div>
</div>
@endsection
