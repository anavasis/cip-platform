<!DOCTYPE html>
<html lang="en">
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
<body class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <div class="font-[family-name:var(--font-display)] text-4xl font-bold text-[var(--color-ink)]">CIP</div>
            <p class="mt-1 text-sm text-slate-600">Content Intelligence Platform</p>
        </div>
        <div class="cip-card">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-teal-200 bg-teal-50 px-3 py-2 text-sm text-teal-900">{{ session('status') }}</div>
            @endif
            @yield('content')
        </div>
    </div>
</body>
</html>
