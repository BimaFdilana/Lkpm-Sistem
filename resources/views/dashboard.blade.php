<x-layouts.app title="Dashboard · Sistem Target Investasi">
    <section class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="page-eyebrow">Monitoring {{ $target?->year ?? 2026 }} · {{ $target?->quarter ?? 'TW III' }}</p>
            <h2 class="page-title">
                @if ($role === 'kepala_dinas') Ringkasan eksekutif investasi
                @elseif ($role === 'kepala_bagian') Kendali operasional LKPM
                @elseif ($role === 'pic') Tugas tindak lanjut perusahaan
                @else Kesiapan data dan integrasi
                @endif
            </h2>
            <p class="page-copy">
                @if ($role === 'kepala_dinas') Pantau capaian target, jadwal, serta kinerja PIC; target tahunan dapat diperbarui dari dashboard.
                @elseif ($role === 'kepala_bagian') Kelola data, pembagian PIC, dan tindak lanjut perusahaan sebelum periode pelaporan.
                @elseif ($role === 'pic') Fokus pada perusahaan yang menjadi tanggung jawab Anda pada periode ini.
                @else Pantau kesiapan data sumber sebelum dipakai oleh tim operasional.
                @endif
            </p>
        </div>
        @if ($role === 'kepala_bagian')<div class="text-right">@if($latestPrioritySnapshot)<p class="mb-2 text-xs font-semibold text-slate-500">Snapshot terakhir {{ $latestPrioritySnapshot->snapshot_date->translatedFormat('d M Y') }}@if($latestPrioritySnapshot->importBatch) · batch #{{ $latestPrioritySnapshot->importBatch->id }}@endif</p>@else<p class="mb-2 text-xs font-semibold text-amber-700">Belum ada snapshot periode aktif</p>@endif<form method="POST" action="{{ route('priority.snapshot') }}">@csrf<button class="primary-action disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled(! $target?->is_active)>{{ $target?->is_active ? 'Buat Snapshot Hari Ini' : 'Aktifkan Periode Terlebih Dahulu' }} <span aria-hidden="true">→</span></button></form></div>
        @elseif ($role === 'pic')<a href="{{ route('assignments.index') }}" class="primary-action">Buka tugas saya <span aria-hidden="true">→</span></a>
        @elseif ($role === 'programmer')<a href="{{ route('imports.index') }}" class="primary-action">Buka impor data <span aria-hidden="true">→</span></a>@endif
    </section>

    @if ($role === 'kepala_dinas')
        @include('dashboard.kadis')
    @else
    @if (false)
        <section class="mt-7 overflow-hidden rounded-3xl bg-ink p-6 text-white shadow-xl shadow-slate-900/10 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-5"><div><p class="text-xs font-bold uppercase tracking-[.16em] text-teal-300">Posisi target tahunan</p><h3 class="mt-3 text-2xl font-bold tracking-tight">Rp {{ number_format($annualRemaining / 1000000000000, 2, ',', '.') }} T masih perlu dikejar</h3><p class="mt-2 max-w-xl text-sm leading-6 text-slate-300">Target tahunan Rp {{ number_format($annualTarget / 1000000000000, 1, ',', '.') }} T, dengan realisasi dasar sebelum periode sebesar Rp {{ number_format($baselineRealization / 1000000000000, 2, ',', '.') }} T.</p></div><span class="status-pill bg-white/10 text-teal-200">{{ $target?->quarter ?? 'TW III' }} {{ $target?->year ?? 2026 }}</span></div>
            <div class="mt-7 grid gap-3 sm:grid-cols-3"><div class="rounded-2xl bg-white/8 p-4"><p class="text-xs text-slate-300">Target TW III</p><p class="mt-2 text-2xl font-bold">Rp {{ number_format($quarterTarget / 1000000000000, 3, ',', '.') }} T</p></div><div class="rounded-2xl bg-white/8 p-4"><p class="text-xs text-slate-300">Realisasi valid TW III</p><p class="mt-2 text-2xl font-bold">Rp {{ number_format($validRealization / 1000000000, 1, ',', '.') }} M</p></div><div class="rounded-2xl bg-white/8 p-4"><p class="text-xs text-slate-300">Kemajuan target TW III</p><p class="mt-2 text-2xl font-bold">{{ number_format($targetProgress, 1, ',', '.') }}%</p></div></div>
            <div class="mt-6"><div class="flex justify-between text-xs font-semibold text-slate-300"><span>Realisasi valid dibanding target TW III</span><span>{{ number_format($targetProgress, 1, ',', '.') }}%</span></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10"><span class="block h-full rounded-full bg-teal-300" style="width: {{ $targetProgress }}%"></span></div></div>
        </section>

        <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="surface-card p-5"><p class="text-sm font-medium text-slate-500">Perusahaan termuat</p><p class="mt-3 text-3xl font-bold tracking-tight text-ink">{{ number_format($companies) }}</p><p class="mt-2 text-xs text-slate-500">basis pemantauan operasi</p></article>
            <article class="surface-card p-5"><p class="text-sm font-medium text-slate-500">Assignment aktif</p><p class="mt-3 text-3xl font-bold tracking-tight text-ink">{{ number_format($assignments) }}</p><p class="mt-2 text-xs text-slate-500">perusahaan telah dibagi ke PIC</p></article>
            <article class="surface-card p-5"><p class="text-sm font-medium text-slate-500">Laporan Disetujui</p><p class="mt-3 text-3xl font-bold tracking-tight text-brand">{{ number_format($approvedReports) }} <span class="text-base text-slate-400">/ {{ number_format($reportCount) }}</span></p><p class="mt-2 text-xs text-slate-500">{{ number_format($approvalRate, 1, ',', '.') }}% pada TW III</p></article>
            <article class="surface-card {{ $unlinkedReports > 0 ? 'border-amber-200' : '' }} p-5"><p class="text-sm font-medium text-slate-500">Perlu rekonsiliasi</p><p class="mt-3 text-3xl font-bold tracking-tight {{ $unlinkedReports > 0 ? 'text-amber-700' : 'text-ink' }}">{{ number_format($unlinkedReports) }}</p><p class="mt-2 text-xs text-slate-500">laporan belum tertaut proyek</p></article>
        </section>

        <section class="mt-5 grid gap-4 sm:grid-cols-3"><article class="surface-card p-5"><p class="text-sm font-medium text-slate-500">Perusahaan sudah dikontak</p><p class="mt-3 text-3xl font-bold text-ink">{{ number_format($contactedCompanies) }}</p><p class="mt-2 text-xs text-slate-500">dari {{ number_format($assignments) }} assignment aktif</p></article><article class="surface-card p-5"><p class="text-sm font-medium text-slate-500">Konfirmasi PIC</p><p class="mt-3 text-3xl font-bold text-brand">{{ number_format($confirmedCompanies) }}</p><p class="mt-2 text-xs text-slate-500">perusahaan telah memberi konfirmasi</p></article><article class="surface-card border-amber-200 p-5"><p class="text-sm font-medium text-slate-500">Nilai indikatif konfirmasi</p><p class="mt-3 text-2xl font-bold text-amber-700">Rp {{ number_format($indicatedAmount / 1000000000, 1, ',', '.') }} M</p><p class="mt-2 text-xs text-slate-500">bukan realisasi resmi LKPM</p></article></section>

        <section class="mt-5 rounded-2xl border {{ $prioritySummary['risk'] === 'aman' ? 'border-teal-200 bg-teal-50' : ($prioritySummary['risk'] === 'kritis' ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50') }} p-6"><div class="flex flex-wrap items-start justify-between gap-4"><div><span class="status-pill bg-white {{ $prioritySummary['risk'] === 'aman' ? 'text-brand' : ($prioritySummary['risk'] === 'kritis' ? 'text-rose-700' : 'text-amber-800') }}">Risiko {{ ucfirst($prioritySummary['risk']) }}</span><h3 class="mt-3 text-lg font-bold text-ink">Proyeksi harian untuk keputusan Kadis</h3><p class="mt-1 text-sm text-slate-600">{{ $prioritySummary['date'] ? 'Snapshot '.\Carbon\Carbon::parse($prioritySummary['date'])->translatedFormat('d M Y') : 'Belum ada snapshot harian.' }}</p></div><div class="text-right"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Checkpoint</p><p class="mt-1 text-lg font-bold text-ink">{{ $prioritySummary['checkpoint'] === null ? 'Belum diatur' : 'H-'.$prioritySummary['checkpoint'] }}</p><a href="{{ route('priority.index') }}" class="mt-2 block text-sm font-bold text-brand hover:underline">Buka rincian prioritas →</a></div></div><div class="mt-5 grid gap-3 sm:grid-cols-4"><div class="rounded-xl bg-white/70 p-4"><p class="text-xs text-slate-500">Sisa target valid</p><p class="mt-2 text-xl font-bold text-ink">Rp {{ number_format($prioritySummary['remainingTarget'] / 1000000000, 1, ',', '.') }} M</p>@if($prioritySummary['remainingChange'] !== null)<p class="mt-1 text-xs {{ $prioritySummary['remainingChange'] <= 0 ? 'text-brand' : 'text-rose-700' }}">{{ $prioritySummary['remainingChange'] <= 0 ? 'Turun' : 'Naik' }} Rp {{ number_format(abs($prioritySummary['remainingChange']) / 1000000000, 1, ',', '.') }} M dari snapshot sebelumnya</p>@endif</div><div class="rounded-xl bg-white/70 p-4"><p class="text-xs text-slate-500">Proyeksi Hijau</p><p class="mt-2 text-xl font-bold text-ink">Rp {{ number_format($prioritySummary['greenProjection'] / 1000000000, 1, ',', '.') }} M</p></div><div class="rounded-xl bg-white/70 p-4"><p class="text-xs text-slate-500">Amber / Cadangan</p><p class="mt-2 text-xl font-bold text-ink">{{ $prioritySummary['amberCount'] }} / {{ $prioritySummary['reserveCount'] }}</p></div><div class="rounded-xl bg-white/70 p-4"><p class="text-xs text-slate-500">Aksi checkpoint</p><p class="mt-2 text-sm font-bold text-ink">{{ $prioritySummary['checkpoint'] !== null && $prioritySummary['checkpoint'] <= 7 ? 'Tinjau risiko & cadangan' : 'Pantau verifikasi PIC' }}</p></div></div></section>

        <section class="mt-5 grid gap-5 lg:grid-cols-[1.2fr_.8fr]">
            <article class="surface-card p-6"><p class="page-eyebrow">Jadwal pengawasan</p><h3 class="mt-2 text-lg font-bold text-ink">Periode kerja TW III</h3><div class="mt-5 grid gap-4 sm:grid-cols-2"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Masa kegiatan</p><p class="mt-2 font-bold text-ink">{{ $target?->activity_starts_at?->translatedFormat('d M') ?? '01 Jul' }} – {{ $target?->activity_ends_at?->translatedFormat('d M Y') ?? '30 Sep 2026' }}</p><p class="mt-1 text-xs text-slate-500">pendampingan dan pengawasan PIC</p></div><div class="rounded-xl bg-teal-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-brand">Masa pelaporan OSS</p><p class="mt-2 font-bold text-teal-950">{{ $target?->reporting_starts_at?->translatedFormat('d M') ?? '01 Okt' }} – {{ $target?->reporting_ends_at?->translatedFormat('d M Y') ?? '15 Okt 2026' }}</p><p class="mt-1 text-xs text-teal-800">periode perusahaan menyampaikan LKPM</p></div></div></article>
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-6"><span class="status-pill bg-white text-amber-800">Catatan pimpinan</span><h3 class="mt-4 text-lg font-bold text-amber-950">Keputusan tetap berbasis data valid.</h3><p class="mt-2 text-sm leading-6 text-amber-900">Realisasi hanya dihitung dari LKPM berstatus Disetujui. Nilai gap menunjukkan potensi tindak lanjut, bukan jaminan realisasi.</p></article>
        </section>
    @elseif ($role === 'pic')
        <section class="mt-7 grid gap-4 sm:grid-cols-2">
            <article class="overflow-hidden rounded-2xl bg-ink p-6 text-white shadow-lg shadow-slate-900/10">
                <p class="text-sm font-medium text-teal-200">Tugas aktif saya</p>
                <p class="mt-3 text-5xl font-bold tracking-tight">{{ number_format($myAssignments ?? 0) }}</p>
                <p class="mt-2 text-sm text-slate-300">perusahaan perlu dihubungi</p>
                <a href="{{ route('assignments.index') }}" class="mt-6 inline-flex text-sm font-bold text-teal-300 hover:text-white">Lihat daftar tugas →</a>
            </article>
            <article class="surface-card border-amber-200 bg-amber-50 p-6">
                <span class="status-pill bg-amber-100 text-amber-800">Aksi berikutnya</span>
                <h3 class="mt-4 text-xl font-bold tracking-tight text-ink">Perbarui hasil kontak setiap perusahaan.</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Catatan yang rapi membantu Kepala Bagian menentukan tindak lanjut dan verifikasi lapangan.</p>
            </article>
        </section>
    @else
        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl bg-ink p-5 text-white shadow-lg shadow-slate-900/10"><p class="text-sm font-medium text-slate-300">Target periode</p><p class="mt-3 text-3xl font-bold tracking-tight">Rp {{ number_format(($target?->target_amount ?? 2545000000000) / 1000000000000, 3, ',', '.') }} T</p><p class="mt-3 text-xs font-medium text-teal-300">{{ $target?->quarter ?? 'TW III' }} {{ $target?->year ?? 2026 }}</p></article>
            <article class="surface-card p-5"><p class="text-sm font-medium text-slate-500">Realisasi LKPM Disetujui</p><p class="mt-3 text-3xl font-bold tracking-tight text-ink">Rp {{ number_format($validRealization / 1000000000, 1, ',', '.') }} M</p><div class="mt-4 flex items-center justify-between text-xs font-semibold text-slate-500"><span>Capaian target</span><span>{{ number_format($targetProgress, 1, ',', '.') }}%</span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-teal-50"><span class="block h-full rounded-full bg-brand transition-all" style="width: {{ $targetProgress }}%"></span></div></article>
            <article class="surface-card border-amber-200 p-5"><p class="text-sm font-medium text-slate-500">Sisa target periode</p><p class="mt-3 text-3xl font-bold tracking-tight text-amber-700">Rp {{ number_format($targetGap / 1000000000, 1, ',', '.') }} M</p><p class="mt-4 text-xs font-semibold text-amber-700">Nilai yang masih perlu dikejar</p></article>
            <article class="surface-card p-5"><p class="text-sm font-medium text-slate-500">{{ $role === 'programmer' ? 'Data perusahaan siap' : 'Perusahaan dalam tugas PIC' }}</p><p class="mt-3 text-3xl font-bold tracking-tight text-ink">{{ number_format($role === 'programmer' ? $companies : $assignments) }}</p><p class="mt-4 text-xs text-slate-500">{{ $role === 'programmer' ? 'siap diproses operasional' : 'dari '.number_format($companies).' perusahaan' }}</p></article>
        </section>
    @endif
    @endif

    @if ($role === 'programmer')
        <section class="surface-card mt-7 overflow-hidden"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><p class="page-eyebrow">Aktivitas</p><h3 class="mt-1 font-bold text-ink">Lima impor terbaru</h3></div><a href="{{ route('imports.index') }}" class="text-sm font-bold text-brand hover:underline">Kelola impor</a></div><div class="divide-y divide-slate-100">@forelse ($importBatches as $batch)<div class="flex items-center justify-between gap-4 px-5 py-4 text-sm"><div class="min-w-0"><p class="truncate font-bold text-ink">{{ $batch->original_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $batch->created_at->format('d M Y · H:i') }}</p></div><span class="status-pill bg-slate-100 text-slate-600">{{ $batch->status }}</span></div>@empty<p class="px-5 py-10 text-sm text-slate-500">Belum ada file impor.</p>@endforelse</div></section>
    @elseif ($role === 'kepala_bagian')
        <section class="mt-7 grid gap-5 lg:grid-cols-2">
            <article class="surface-card p-6"><span class="status-pill bg-amber-100 text-amber-800">Perlu perhatian</span><p class="mt-5 text-4xl font-bold tracking-tight text-ink">{{ number_format($pendingReports) }}</p><h3 class="mt-2 font-bold text-ink">Laporan belum Disetujui</h3><p class="mt-1 text-sm leading-6 text-slate-600">Gunakan daftar assignment untuk mengarahkan tindak lanjut perusahaan.</p></article>
            <article class="rounded-2xl border border-teal-200 bg-teal-50 p-6"><span class="status-pill bg-white text-brand">Aturan realisasi</span><h3 class="mt-4 text-lg font-bold text-teal-950">Hanya laporan Disetujui yang dihitung.</h3><p class="mt-2 text-sm leading-6 text-teal-900">Proyek tanpa riwayat LKPM tetap masuk antrean verifikasi; sistem tidak langsung menganggap realisasinya sebagai angka valid Rp0.</p></article>
        </section>
    @endif
</x-layouts.app>
