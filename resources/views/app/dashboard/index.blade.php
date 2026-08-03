@extends('layouts.app')
@section('title', 'Dashboard — CIP')
@section('breadcrumbs') Dashboard @endsection
@section('content')
<h1 class="mb-6 font-[family-name:var(--font-display)] text-3xl font-bold">Operational dashboard</h1>
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
@foreach($stats as $label => $value)
    <div class="cip-card">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $label) }}</div>
        <div class="mt-2 text-3xl font-bold">{{ $value }}</div>
    </div>
@endforeach
</div>
<div class="grid gap-4 lg:grid-cols-2">
    <div class="cip-card">
        <h2 class="mb-3 text-lg font-semibold">Queue health</h2>
        <ul class="space-y-1 text-sm">
            <li>Pending: {{ $queue['pending'] }}</li>
            <li>Running: {{ $queue['running'] }}</li>
            <li>Failed: {{ $queue['failed'] }}</li>
        </ul>
    </div>
    <div class="cip-card">
        <h2 class="mb-3 text-lg font-semibold">Diagnostics summary</h2>
        <div class="text-sm">Status: <span class="cip-badge">{{ $health['status'] }}</span></div>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach($health['checks'] as $name => $check)
                <li>{{ $name }}: {{ $check['status'] }} — {{ $check['message'] ?? '' }}</li>
            @endforeach
        </ul>
    </div>
</div>
<div class="cip-card mt-4">
    <h2 class="mb-3 text-lg font-semibold">Recent activity</h2>
    <table class="cip-table">
        <thead><tr><th>When</th><th>Event</th></tr></thead>
        <tbody>
        @forelse($recentEvents as $event)
            <tr>
                <td>{{ $event->occurred_at }}</td>
                <td>{{ $event->event_type }}</td>
            </tr>
        @empty
            <tr><td colspan="2">No recent events.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
