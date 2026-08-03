<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CIP Platform')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&family=Source+Serif+4:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="cip-shell">
    <aside id="cip-sidebar" class="cip-sidebar px-4 py-6">
        <div class="mb-8 flex items-start justify-between gap-2 px-2">
            <div>
                <div class="font-[family-name:var(--font-display)] text-2xl font-bold tracking-tight text-white">CIP</div>
                <div class="text-xs uppercase tracking-[0.2em] text-teal-200/80">Operational Console</div>
            </div>
            <button type="button" class="cip-btn-secondary lg:hidden" onclick="document.getElementById('cip-sidebar').classList.remove('is-open')">Close</button>
        </div>
        <nav class="space-y-1">
            @php
                $nav = [
                    ['route' => 'app.dashboard', 'label' => 'Dashboard', 'match' => 'app.dashboard'],
                    ['route' => 'app.sources.index', 'label' => 'Sources', 'match' => 'app.sources.*'],
                    ['route' => 'app.acquisition.index', 'label' => 'Acquisition', 'match' => 'app.acquisition.*'],
                    ['route' => 'app.announcements.index', 'label' => 'Announcements', 'match' => 'app.announcements.*'],
                    ['route' => 'app.queue.index', 'label' => 'Queue', 'match' => 'app.queue.*'],
                    ['route' => 'app.diagnostics', 'label' => 'Diagnostics', 'match' => 'app.diagnostics'],
                    ['route' => 'app.settings.edit', 'label' => 'Settings', 'match' => 'app.settings.*'],
                ];
            @endphp
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="cip-nav-link {{ request()->routeIs($item['match']) ? 'cip-nav-link-active' : '' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </aside>

    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-10 border-b border-[var(--color-line)] bg-[color-mix(in_srgb,var(--color-panel)_88%,white)] backdrop-blur">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-8">
                <div class="flex items-center gap-3">
                    <button type="button" class="cip-btn-secondary lg:hidden" onclick="document.getElementById('cip-sidebar').classList.add('is-open')">Menu</button>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">Workspace</div>
                        <form method="POST" action="{{ route('app.context.store') }}" class="mt-1 flex flex-wrap items-center gap-2">
                            @csrf
                            <select class="cip-input !w-auto min-w-[10rem]" name="organization_id" id="top-org" onchange="filterProjects()">
                                @foreach(($operatorOrganizations ?? collect()) as $org)
                                    <option value="{{ $org->id }}" @selected(($currentOrganization->id ?? null) === $org->id)>{{ $org->name }}</option>
                                @endforeach
                            </select>
                            <select class="cip-input !w-auto min-w-[10rem]" name="project_id" id="top-project">
                                @foreach(($operatorProjects ?? collect()) as $project)
                                    <option value="{{ $project->id }}" data-org="{{ $project->organization_id }}" @selected(($currentProject->id ?? null) === $project->id)>{{ $project->name }}</option>
                                @endforeach
                            </select>
                            <button class="cip-btn-secondary" type="submit">Apply</button>
                        </form>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <details class="relative">
                        <summary class="cip-btn-secondary cursor-pointer list-none">
                            Notifications
                            @php $noteCount = ($operatorNotifications ?? collect())->count(); @endphp
                            @if($noteCount > 0)
                                <span class="cip-badge ml-1">{{ $noteCount }}</span>
                            @endif
                        </summary>
                        <div class="absolute right-0 z-20 mt-2 w-80 rounded-md border border-[var(--color-line)] bg-white p-3 text-sm shadow-lg">
                            @forelse(($operatorNotifications ?? collect()) as $note)
                                <div class="border-b border-[var(--color-line)] py-2 last:border-0">
                                    <div class="font-semibold">{{ $note->job_type }}</div>
                                    <div class="text-slate-600">{{ $note->error ?: 'failed' }}</div>
                                </div>
                            @empty
                                <div class="text-slate-600">No failure notifications.</div>
                            @endforelse
                            <a class="mt-2 inline-block" href="{{ route('app.diagnostics') }}">Open diagnostics</a>
                        </div>
                    </details>
                    <button type="button" class="cip-btn-secondary" onclick="toggleTheme()">Theme</button>
                    <details class="relative">
                        <summary class="cip-btn-secondary cursor-pointer list-none">{{ auth()->user()->name }}</summary>
                        <div class="absolute right-0 z-20 mt-2 w-48 rounded-md border border-[var(--color-line)] bg-white p-2 text-sm shadow-lg">
                            <a class="block rounded px-2 py-1 hover:bg-slate-50" href="{{ route('app.profile.edit') }}">Profile</a>
                            <a class="block rounded px-2 py-1 hover:bg-slate-50" href="{{ route('app.settings.edit') }}">Settings</a>
                            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                                @csrf
                                <button class="w-full rounded px-2 py-1 text-left hover:bg-slate-50" type="submit">Logout</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
            @hasSection('breadcrumbs')
                <div class="border-t border-[var(--color-line)] px-4 py-2 text-sm text-slate-600 lg:px-8">
                    @yield('breadcrumbs')
                </div>
            @endif
        </header>

        <main class="flex-1 px-4 py-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script>
(function () {
  const key = 'cip-theme';
  const saved = localStorage.getItem(key) || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  window.toggleTheme = function () {
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem(key, next);
  };
  window.filterProjects = function () {
    const org = document.getElementById('top-org').value;
    const select = document.getElementById('top-project');
    let firstVisible = null;
    Array.from(select.options).forEach((opt) => {
      const show = opt.getAttribute('data-org') === org;
      opt.hidden = !show;
      if (show && firstVisible === null) firstVisible = opt.value;
    });
    const current = select.selectedOptions[0];
    if (!current || current.hidden) select.value = firstVisible || '';
  };
  filterProjects();
})();
</script>
@stack('scripts')
</body>
</html>
