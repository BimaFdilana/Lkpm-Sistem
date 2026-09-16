<x-layouts.app title="Prioritas Harian TW · Sistem Target Investasi">
    <section class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="page-eyebrow">Fungsi 5 · Mode simulasi</p>
            <h2 class="page-title">Mesin Prioritas Harian TW</h2>
            <p class="page-copy">Rekomendasi per proyek dari LKPM valid, riwayat valid, dan konfirmasi PIC. Tidak ada assignment PIC yang diubah dari halaman ini.</p>
        </div>
        @if (auth()->user()->role === 'kepala_bagian')
            <form method="POST" action="{{ route('priority.snapshot') }}">@csrf<button class="primary-action" type="submit">Buat snapshot hari ini <span aria-hidden="true">→</span></button></form>
        @endif
    </section>

    <section class="mt-7 grid gap-4 sm:grid-cols-3">
        <article class="surface-card p-5"><p class="text-sm text-slate-500">Periode aktif</p><p class="mt-2 text-xl font-bold text-ink">{{ $period?->quarter ?? 'Belum aktif' }} {{ $period?->year }}</p></article>
        <article class="surface-card p-5"><p class="text-sm text-slate-500">Snapshot terbaru</p><p class="mt-2 text-xl font-bold text-ink">{{ $latestDate ? \Carbon\Carbon::parse($latestDate)->translatedFormat('d M Y') : 'Belum tersedia' }}</p></article>
        <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><p class="text-sm text-amber-800">Pengaman</p><p class="mt-2 text-sm font-semibold text-amber-950">Snapshot tidak dapat ditimpa; rekomendasi tidak mengaktifkan cadangan otomatis.</p></article>
    </section>
    @if($summary)<section class="mt-5 grid gap-4 sm:grid-cols-3"><article class="surface-card p-5"><p class="text-sm text-slate-500">Sisa target valid</p><p class="mt-2 text-2xl font-bold text-amber-700">Rp {{ number_format($summary['remaining_target'] / 1000000000, 1, ',', '.') }} M</p></article><article class="surface-card p-5"><p class="text-sm text-slate-500">Momentum valid TW</p><p class="mt-2 text-2xl font-bold text-brand">Rp {{ number_format($summary['valid_momentum'] / 1000000000, 1, ',', '.') }} M</p></article><article class="surface-card p-5"><p class="text-sm text-slate-500">Proyeksi Hijau</p><p class="mt-2 text-2xl font-bold text-ink">Rp {{ number_format($summary['green_projection'] / 1000000000, 1, ',', '.') }} M</p><p class="mt-1 text-xs text-slate-500">batas sasaran utama: 130% sisa target</p></article></section>@endif

    <section class="surface-card mt-5 overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4"><p class="page-eyebrow">Urutan rekomendasi</p><h3 class="mt-1 font-bold text-ink">Hijau, cadangan, dan amber</h3></div>
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Prioritas</th><th class="px-5 py-3">Perusahaan / proyek</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Proyeksi TW</th><th class="px-5 py-3">Dasar</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($snapshots as $snapshot)<tr><td class="px-5 py-4 font-bold text-ink">{{ $snapshot->priority_rank ? '#'.$snapshot->priority_rank : '—' }}</td><td class="px-5 py-4"><p class="font-semibold text-ink">{{ $snapshot->company->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $snapshot->project->project_code }}</p></td><td class="px-5 py-4"><span class="status-pill {{ $snapshot->candidate_tier === 'hijau' ? 'bg-teal-100 text-teal-800' : ($snapshot->candidate_tier === 'amber' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">{{ ucfirst($snapshot->candidate_tier) }}</span><p class="mt-1 text-xs text-slate-500">{{ str_replace('_', ' ', $snapshot->verification_status) }} · skor {{ number_format($snapshot->priority_score ?? 0, 2, ',', '.') }}</p></td><td class="px-5 py-4 text-right font-semibold text-ink">{{ $snapshot->projected_contribution === null ? 'Belum diketahui' : 'Rp '.number_format($snapshot->projected_contribution / 1000000000, 1, ',', '.').' M' }}</td><td class="px-5 py-4 text-xs text-slate-600">{{ str_replace('_', ' ', $snapshot->projection_source) }}</td></tr>@empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada snapshot. Jalankan setelah impor data pagi atau buat snapshot simulasi.</td></tr>@endforelse</tbody></table></div>
        @if($snapshots->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $snapshots->links() }}</div>@endif
    </section>
</x-layouts.app>
