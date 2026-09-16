<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Sistem Target Investasi</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-ink p-4 sm:p-6">
    <main class="grid w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl shadow-slate-950/30 md:grid-cols-[.9fr_1.1fr]">
        <section class="hidden bg-brand p-10 text-white md:flex md:flex-col"><div class="grid size-12 place-items-center rounded-2xl bg-white font-black text-brand">SI</div><div class="my-auto"><p class="text-xs font-bold uppercase tracking-[.2em] text-teal-100">DPMPTSP Bengkalis</p><h1 class="mt-4 text-4xl font-bold leading-tight tracking-tight">Kejar target investasi dengan data yang terarah.</h1><p class="mt-5 max-w-sm text-sm leading-7 text-teal-50">Satu ruang kerja untuk memantau LKPM, membagi tugas PIC, dan menjaga tindak lanjut perusahaan.</p></div><p class="text-xs text-teal-100">Sistem Target Investasi · Satgas LKPM</p></section>
        <section class="p-7 sm:p-10"><div class="md:hidden"><span class="grid size-10 place-items-center rounded-xl bg-ink text-sm font-black text-teal-300">SI</span><p class="mt-5 text-xs font-bold uppercase tracking-[.16em] text-brand">DPMPTSP Bengkalis</p></div><p class="hidden text-xs font-bold uppercase tracking-[.16em] text-brand md:block">Akses internal</p><h2 class="mt-3 text-3xl font-bold tracking-tight text-ink">Masuk ke sistem</h2><p class="mt-2 text-sm leading-6 text-slate-600">Gunakan akun internal yang telah ditetapkan untuk peran Anda.</p>
            <form method="POST" action="{{ route('login.store') }}" class="mt-8 grid gap-5">@csrf<label class="grid gap-1.5 text-sm font-bold text-slate-700">Email<input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="rounded-xl px-3 py-2.5 text-slate-800" placeholder="nama@dpmptsp.go.id"></label><label class="grid gap-1.5 text-sm font-bold text-slate-700">Kata sandi<input name="password" type="password" required autocomplete="current-password" class="rounded-xl px-3 py-2.5 text-slate-800" placeholder="••••••••"></label>@error('email')<p class="-mt-2 text-sm font-medium text-coral">{{ $message }}</p>@enderror<button class="primary-action mt-1 w-full py-3">Masuk ke dashboard <span aria-hidden="true">→</span></button></form>
        </section>
    </main>
</body>
</html>
