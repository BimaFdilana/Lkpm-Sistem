<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Sistem Target Investasi</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans text-slate-800 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <div class="w-full max-w-md">
            <div class="mb-7 flex items-center justify-center gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-ink text-sm font-black text-teal-300" aria-hidden="true">SI</span>
                <div>
                    <p class="text-sm font-bold leading-5 text-ink">Sistem Target Investasi</p>
                    <p class="text-xs text-slate-600">DPMPTSP Bengkalis</p>
                </div>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="login-title">
                <h1 id="login-title" class="text-2xl font-bold tracking-tight text-ink">Masuk</h1>
                <p class="mt-2 text-sm text-slate-600">Gunakan akun yang diberikan kepada Anda.</p>

                @if (session('status'))
                    <p class="mt-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900" role="status">{{ session('status') }}</p>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror class="w-full rounded-xl px-3 py-2.5 text-slate-800">
                        @error('email')<p id="email-error" class="mt-1.5 text-sm text-coral" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Kata sandi</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror class="w-full rounded-xl px-3 py-2.5 text-slate-800">
                        @error('password')<p id="password-error" class="mt-1.5 text-sm text-coral" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="primary-action w-full py-3">Masuk</button>
                </form>
            </section>

            <p class="mt-6 text-center text-xs text-slate-500">Akses internal DPMPTSP Bengkalis</p>
        </div>
    </main>
</body>
</html>
