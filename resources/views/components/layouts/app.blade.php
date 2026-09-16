<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sistem Target Investasi Satgas' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans text-slate-800 antialiased">
    <div class="min-h-screen md:grid md:grid-cols-[16rem_1fr] xl:grid-cols-[18rem_1fr]">
        <aside class="hidden min-h-screen flex-col bg-ink px-4 py-5 text-slate-200 md:flex">
            <div class="flex items-center gap-3 px-3">
                <div class="grid size-10 place-items-center rounded-xl bg-teal-400 font-black text-ink shadow-lg shadow-teal-950/20">SI</div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[.18em] text-teal-300">DPMPTSP Bengkalis</p>
                    <h1 class="mt-1 text-sm font-bold text-white">Target Investasi</h1>
                </div>
            </div>

            <nav class="mt-9 grid gap-1 text-sm" aria-label="Navigasi utama">
                <a class="app-sidebar-link" @if(request()->routeIs('dashboard')) aria-current="page" @endif href="{{ route('dashboard') }}"><span class="grid size-6 place-items-center rounded-md bg-white/10 text-[10px] font-bold">01</span>Dashboard</a>
                @if (auth()->user()->isOneOf('kepala_bagian', 'pic'))
                    <a class="app-sidebar-link" @if(request()->routeIs('assignments.*')) aria-current="page" @endif href="{{ route('assignments.index') }}"><span class="grid size-6 place-items-center rounded-md bg-white/10 text-[10px] font-bold">02</span>{{ auth()->user()->role === 'pic' ? 'Tugas Saya' : 'Assignment PIC' }}</a>
                @endif
                @if (auth()->user()->isOneOf('kepala_bagian', 'kepala_dinas'))
                    <a class="app-sidebar-link" @if(request()->routeIs('priority.*')) aria-current="page" @endif href="{{ route('priority.index') }}"><span class="grid size-6 place-items-center rounded-md bg-white/10 text-[10px] font-bold">03</span>Prioritas Harian</a>
                @endif
                @if (auth()->user()->isOneOf('programmer', 'kepala_bagian'))
                    <a class="app-sidebar-link" @if(request()->routeIs('imports.*')) aria-current="page" @endif href="{{ route('imports.index') }}"><span class="grid size-6 place-items-center rounded-md bg-white/10 text-[10px] font-bold">03</span>Impor Data</a>
                @endif
                @if (auth()->user()->role === 'kepala_bagian')
                    <a class="app-sidebar-link" @if(request()->routeIs('integrated-data.*')) aria-current="page" @endif href="{{ route('integrated-data.index') }}"><span class="grid size-6 place-items-center rounded-md bg-white/10 text-[10px] font-bold">02</span>Data Terpadu</a>
                    <a class="app-sidebar-link" @if(request()->routeIs('pics.*')) aria-current="page" @endif href="{{ route('pics.index') }}"><span class="grid size-6 place-items-center rounded-md bg-white/10 text-[10px] font-bold">04</span>Kelola PIC</a>
                    <a class="app-sidebar-link" @if(request()->routeIs('periods.*')) aria-current="page" @endif href="{{ route('periods.index') }}"><span class="grid size-6 place-items-center rounded-md bg-white/10 text-[10px] font-bold">05</span>Periode & Target</a>
                    <a class="app-sidebar-link" @if(request()->routeIs('snapshots.*')) aria-current="page" @endif href="{{ route('snapshots.index') }}"><span class="grid size-6 place-items-center rounded-md bg-white/10 text-[10px] font-bold">07</span>Snapshot Harian</a>
                    <a class="app-sidebar-link" @if(request()->routeIs('reconciliations.*')) aria-current="page" @endif href="{{ route('reconciliations.index') }}"><span class="grid size-6 place-items-center rounded-md bg-white/10 text-[10px] font-bold">06</span>Rekonsiliasi</a>
                @endif
            </nav>

            <div class="mt-auto rounded-2xl border border-white/10 bg-white/[.06] p-4 text-xs">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-full bg-amber text-sm font-black text-ink">{{ str(auth()->user()->name)->substr(0, 1) }}</span>
                    <div class="min-w-0"><p class="truncate font-bold text-white">{{ auth()->user()->name }}</p><p class="mt-0.5 capitalize text-slate-400">{{ str_replace('_', ' ', auth()->user()->role) }}</p></div>
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-mist/90 px-4 py-3 backdrop-blur md:hidden">
                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2"><span class="grid size-9 place-items-center rounded-xl bg-ink text-xs font-black text-teal-300">SI</span><span class="text-sm font-bold text-ink">Target Investasi</span></a>
                    <div class="flex items-center gap-2"><form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-coral shadow-sm">Keluar</button></form><button type="button" data-nav-toggle aria-controls="mobile-navigation" aria-expanded="false" class="grid size-10 place-items-center rounded-xl border border-slate-200 bg-white text-ink shadow-sm"><span class="sr-only">Buka navigasi</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button></div>
                </div>
                <nav id="mobile-navigation" data-mobile-nav data-state="closed" class="app-mobile-nav mt-3 grid gap-1 rounded-2xl border border-slate-200 bg-white p-2 text-sm shadow-xl" aria-label="Navigasi seluler">
                    <a class="rounded-xl px-3 py-2.5 font-semibold {{ request()->routeIs('dashboard') ? 'bg-teal-50 text-brand' : 'text-slate-700' }}" href="{{ route('dashboard') }}">Dashboard</a>
                    @if (auth()->user()->isOneOf('kepala_bagian', 'pic'))<a class="rounded-xl px-3 py-2.5 font-semibold {{ request()->routeIs('assignments.*') ? 'bg-teal-50 text-brand' : 'text-slate-700' }}" href="{{ route('assignments.index') }}">{{ auth()->user()->role === 'pic' ? 'Tugas Saya' : 'Assignment PIC' }}</a>@endif
                    @if (auth()->user()->isOneOf('kepala_bagian', 'kepala_dinas'))<a class="rounded-xl px-3 py-2.5 font-semibold {{ request()->routeIs('priority.*') ? 'bg-teal-50 text-brand' : 'text-slate-700' }}" href="{{ route('priority.index') }}">Prioritas Harian</a>@endif
                    @if (auth()->user()->isOneOf('programmer', 'kepala_bagian'))<a class="rounded-xl px-3 py-2.5 font-semibold {{ request()->routeIs('imports.*') ? 'bg-teal-50 text-brand' : 'text-slate-700' }}" href="{{ route('imports.index') }}">Impor Data</a>@endif
                    @if (auth()->user()->role === 'kepala_bagian')<a class="rounded-xl px-3 py-2.5 font-semibold {{ request()->routeIs('integrated-data.*') ? 'bg-teal-50 text-brand' : 'text-slate-700' }}" href="{{ route('integrated-data.index') }}">Data Terpadu</a><a class="rounded-xl px-3 py-2.5 font-semibold {{ request()->routeIs('pics.*') ? 'bg-teal-50 text-brand' : 'text-slate-700' }}" href="{{ route('pics.index') }}">Kelola PIC</a><a class="rounded-xl px-3 py-2.5 font-semibold {{ request()->routeIs('periods.*') ? 'bg-teal-50 text-brand' : 'text-slate-700' }}" href="{{ route('periods.index') }}">Periode & Target</a><a class="rounded-xl px-3 py-2.5 font-semibold {{ request()->routeIs('snapshots.*') ? 'bg-teal-50 text-brand' : 'text-slate-700' }}" href="{{ route('snapshots.index') }}">Snapshot Harian</a><a class="rounded-xl px-3 py-2.5 font-semibold {{ request()->routeIs('reconciliations.*') ? 'bg-teal-50 text-brand' : 'text-slate-700' }}" href="{{ route('reconciliations.index') }}">Rekonsiliasi</a>@endif
                </nav>
            </header>

            <main class="min-w-0 px-4 py-6 sm:px-6 md:px-8 md:py-8 xl:px-10">
                <div class="mb-6 hidden items-center justify-end gap-3 md:flex"><div class="text-right"><p class="text-sm font-bold text-ink">{{ auth()->user()->name }}</p><p class="mt-0.5 text-xs capitalize text-slate-500">{{ str_replace('_', ' ', auth()->user()->role) }}</p></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-coral shadow-sm transition hover:border-rose-200 hover:bg-rose-50">Keluar</button></form></div>
                @if (session('status'))<div class="mb-6 flex gap-3 rounded-2xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-950"><span class="font-bold text-brand">✓</span><span>{{ session('status') }}</span></div>@endif
                @if ($errors->any())<div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><ul class="grid gap-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
