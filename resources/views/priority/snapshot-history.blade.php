<section class="mt-5 space-y-5">
    <div class="grid gap-4 sm:grid-cols-3">
        <article class="surface-card p-5">
            <p class="text-sm text-slate-500">Snapshot prioritas terbaru</p>
            <p class="mt-2 text-xl font-bold text-ink">{{ $priorityHistory->first() ? \Illuminate\Support\Carbon::parse($priorityHistory->first()->snapshot_date)->translatedFormat('d M Y') : 'Belum ada' }}</p>
            <p class="mt-1 text-xs text-slate-500">Dibuat oleh Kabag untuk periode aktif.</p>
        </article>
        <article class="surface-card p-5">
            <p class="text-sm text-slate-500">Snapshot data impor terbaru</p>
            <p class="mt-2 text-xl font-bold text-ink">{{ $recentSnapshots->first() ? \Illuminate\Support\Carbon::parse($recentSnapshots->first()->snapshot_date)->translatedFormat('d M Y') : 'Belum ada' }}</p>
            <p class="mt-1 text-xs text-slate-500">Tercatat saat data LKPM selesai diproses.</p>
        </article>
        <article class="surface-card p-5">
            <p class="text-sm text-slate-500">Baseline historis</p>
            <p class="mt-2 text-xl font-bold text-ink">{{ number_format($baselinePeriods->sum('project_count')) }}</p>
            <p class="mt-1 text-xs text-slate-500">Data proyek dan triwulan yang tercatat.</p>
        </article>
    </div>

    <section class="surface-card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="font-bold text-ink">Riwayat snapshot prioritas</h3>
            <p class="mt-1 text-xs text-slate-500">Rekomendasi sasaran yang dibuat untuk {{ $period?->quarter ?? 'periode aktif' }} {{ $period?->year }}. Snapshot pada tanggal yang sama tidak ditimpa.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50/80 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-3.5">Tanggal</th><th class="px-5 py-3.5">Perusahaan</th><th class="px-5 py-3.5 text-right">Proyeksi sasaran Hijau</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($priorityHistory as $item)
                        <tr><td class="px-5 py-4 font-bold text-ink">{{ \Illuminate\Support\Carbon::parse($item->snapshot_date)->translatedFormat('d M Y') }}</td><td class="px-5 py-4 text-slate-700">{{ number_format($item->company_count) }}</td><td class="px-5 py-4 text-right font-bold text-ink">Rp {{ number_format($item->green_projection ?? 0, 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-10 text-center text-slate-500">Belum ada snapshot prioritas. Buat melalui tombol di Dashboard Kabag.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="surface-card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="font-bold text-ink">Riwayat snapshot data impor</h3>
            <p class="mt-1 text-xs text-slate-500">Ringkasan data LKPM yang tersimpan setelah impor selesai. Realisasi valid hanya berasal dari laporan Disetujui.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50/80 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-3.5">Tanggal</th><th class="px-5 py-3.5">Periode</th><th class="px-5 py-3.5">Proyek</th><th class="px-5 py-3.5">LKPM valid</th><th class="px-5 py-3.5 text-right">Akumulasi valid</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentSnapshots as $item)
                        <tr><td class="px-5 py-4 font-bold text-ink">{{ \Illuminate\Support\Carbon::parse($item->snapshot_date)->translatedFormat('d M Y') }}</td><td class="px-5 py-4 text-slate-700">{{ $item->year }} · {{ $item->quarter }}</td><td class="px-5 py-4 text-slate-700">{{ number_format($item->project_count) }}</td><td class="px-5 py-4 text-slate-700">{{ number_format($item->valid_count) }}</td><td class="px-5 py-4 text-right font-bold text-ink">Rp {{ number_format($item->valid_accumulated, 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Belum ada snapshot data impor LKPM.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="surface-card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="font-bold text-ink">Baseline historis per triwulan</h3>
            <p class="mt-1 text-xs text-slate-500">Baseline pertama proyek diberi penanda estimasi karena tidak memiliki laporan sebelumnya.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50/80 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-5 py-3.5">Periode</th><th class="px-5 py-3.5">Proyek tercatat</th><th class="px-5 py-3.5">Baseline lengkap</th><th class="px-5 py-3.5 text-right">Momentum historis</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($baselinePeriods as $item)
                        <tr><td class="px-5 py-4 font-bold text-ink">{{ $item->year }} · {{ $item->quarter }}</td><td class="px-5 py-4 text-slate-700">{{ number_format($item->project_count) }}</td><td class="px-5 py-4 text-slate-700">{{ number_format($item->complete_count) }}</td><td class="px-5 py-4 text-right font-bold text-ink">{{ $item->momentum_amount === null ? '—' : 'Rp '.number_format($item->momentum_amount, 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">Baseline belum dibangun.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
