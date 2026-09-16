<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sistem Target Investasi Satgas' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-mist font-sans text-slate-800">
<div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
    <aside class="bg-ink px-5 py-6 text-slate-200">
        <div class="mb-10"><p class="text-xs font-semibold uppercase tracking-[.2em] text-teal-300">DPMPTSP Bengkalis</p><h1 class="mt-2 text-lg font-semibold text-white">Sistem Target Investasi</h1></div>
        <nav class="grid gap-1 text-sm">
            <a class="rounded-lg px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-white/12 text-white' : 'hover:bg-white/8' }}" href="{{ route('dashboard') }}">Dashboard</a>
            @if(auth()->user()->role === 'kepala_dinas')<a class="rounded-lg px-3 py-2 {{ request()->routeIs('annual-targets.*') ? 'bg-white/12 text-white' : 'hover:bg-white/8' }}" href="{{ route('annual-targets.index') }}">Target Tahunan</a><a class="rounded-lg px-3 py-2 {{ request()->routeIs('priority.*') ? 'bg-white/12 text-white' : 'hover:bg-white/8' }}" href="{{ route('priority.index') }}">Prioritas Harian</a>@endif
            <a class="rounded-lg px-3 py-2 {{ request()->routeIs('assignments.*') ? 'bg-white/12 text-white' : 'hover:bg-white/8' }}" href="{{ route('assignments.index') }}">Assignment PIC</a>
            @if(auth()->user()->isOneOf('programmer', 'kepala_bagian'))<a class="rounded-lg px-3 py-2 {{ request()->routeIs('imports.*') ? 'bg-white/12 text-white' : 'hover:bg-white/8' }}" href="{{ route('imports.index') }}">Impor Data</a>@endif
        </nav>
        <div class="mt-12 rounded-lg border border-white/10 p-3 text-xs leading-5 text-slate-300"><p class="font-medium text-white">{{ auth()->user()->name }}</p><p>{{ str_replace('_', ' ', auth()->user()->role) }}</p><form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="text-teal-300 hover:text-white">Keluar</button></form></div>
    </aside>
    <main class="min-w-0 p-5 lg:p-8">
        @if(session('status'))<div class="mb-5 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ session('status') }}</div>@endif
        {{ $slot }}
    </main>
</div>
</body>
</html>
