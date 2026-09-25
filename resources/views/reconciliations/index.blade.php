<x-layouts.app title="Rekonsiliasi Kode Proyek">
    <section>
        <p class="page-eyebrow">Kepala Bagian</p>
        <h2 class="page-title">Rekonsiliasi dan kelengkapan data</h2>
        <p class="page-copy">Hubungkan kode LKPM ke Id Proyek DP.Proyek hanya setelah diverifikasi dari sumber resmi. Sistem tidak mencocokkan data hanya berdasarkan kemiripan nama perusahaan.</p>
    </section>

    <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan kualitas data">
        <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><p class="text-xs font-bold uppercase tracking-wide text-amber-800">Laporan belum tertaut</p><p class="mt-2 text-3xl font-black text-amber-950">{{ number_format($unlinkedReportCount) }}</p><p class="mt-1 text-xs text-amber-900">dari {{ number_format($unlinkedCodeCount) }} kode proyek LKPM</p></article>
        <article class="surface-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Email belum tersedia</p><p class="mt-2 text-3xl font-black text-ink">{{ number_format($missingEmailCount) }}</p><p class="mt-1 text-xs text-slate-500">perusahaan perlu pelengkapan kontak</p></article>
        <article class="surface-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Telepon belum tersedia</p><p class="mt-2 text-3xl font-black text-ink">{{ number_format($missingPhoneCount) }}</p><p class="mt-1 text-xs text-slate-500">perusahaan perlu verifikasi kontak</p></article>
        <article class="surface-card p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sektor belum tersedia</p><p class="mt-2 text-3xl font-black text-ink">{{ number_format($missingSectorCount) }}</p><p class="mt-1 text-xs text-slate-500">proyek perlu koreksi sumber DP.Proyek</p></article>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[.82fr_1.18fr]">
        <form method="POST" action="{{ route('reconciliations.store') }}" class="surface-card p-6">
            @csrf
            <p class="page-eyebrow">Pemetaan manual</p>
            <h3 class="mt-2 font-bold text-ink">Tambah pemetaan terverifikasi</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">Gunakan dokumen resmi untuk memastikan kedua kode mewakili proyek yang sama.</p>
            <div class="mt-5 grid gap-4">
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">No Kode Proyek LKPM<input name="lkpm_project_code" value="{{ old('lkpm_project_code') }}" required class="rounded-xl" placeholder="Contoh: 202112-1512-..."></label>
                <label class="grid gap-1.5 text-sm font-bold text-slate-700">Id Proyek DP.Proyek<input name="project_code" value="{{ old('project_code') }}" required class="rounded-xl" placeholder="Contoh: R-2019..."></label>
                <button class="primary-action justify-center">Simpan pemetaan</button>
            </div>
        </form>

        <section class="surface-card p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="page-eyebrow">Penyaringan</p><h3 class="mt-2 font-bold text-ink">Kode LKPM yang perlu diperiksa</h3></div>
                <a class="secondary-action" href="{{ route('reconciliations.export', request()->only('year', 'quarter', 'code')) }}">Ekspor CSV</a>
            </div>
            <form method="GET" action="{{ route('reconciliations.index') }}" class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <label class="grid gap-1.5 text-xs font-bold text-slate-600">Tahun<select name="year" class="rounded-xl text-sm"><option value="">Semua tahun</option>@foreach($availableYears as $year)<option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>@endforeach</select></label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-600">Triwulan<select name="quarter" class="rounded-xl text-sm"><option value="">Semua TW</option>@foreach(['Triwulan I', 'Triwulan II', 'Triwulan III', 'Triwulan IV'] as $quarter)<option value="{{ $quarter }}" @selected(request('quarter') === $quarter)>{{ $quarter }}</option>@endforeach</select></label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-600">Kode proyek<input name="code" value="{{ request('code') }}" class="rounded-xl text-sm" placeholder="Cari kode LKPM"></label>
                <div class="flex items-end gap-2"><button class="primary-action flex-1 justify-center">Terapkan</button><a class="secondary-action" href="{{ route('reconciliations.index') }}">Reset</a></div>
            </form>
        </section>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
        <div class="surface-card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-ink">Daftar kode belum terhubung</h3><p class="mt-1 text-xs text-slate-500">Impor DP.Proyek terbaru terlebih dahulu sebelum membuat pemetaan manual.</p></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-sm"><thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Kode LKPM</th><th class="px-5 py-3">Laporan</th><th class="px-5 py-3">Rentang tahun</th><th class="px-5 py-3">Keterangan</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse ($unlinkedCodes as $row)<tr><td class="px-5 py-3 font-mono text-xs text-ink">{{ $row->project_code }}</td><td class="px-5 py-3 font-bold text-ink">{{ number_format($row->report_count) }}</td><td class="px-5 py-3 text-slate-600">{{ $row->first_year }}{{ $row->first_year !== $row->last_year ? '–'.$row->last_year : '' }}</td><td class="px-5 py-3 text-xs text-amber-800">{{ $row->reason }}</td></tr>@empty<tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">Tidak ada kode yang sesuai filter atau seluruh laporan telah terhubung.</td></tr>@endforelse</tbody></table></div>
            <div class="border-t border-slate-100 p-5">{{ $unlinkedCodes->links() }}</div>
        </div>

        <div class="surface-card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-bold text-ink">Pemetaan terbaru</h3><p class="mt-1 text-xs text-slate-500">Riwayat keputusan pemetaan manual Kepala Bagian.</p></div>
            <div class="divide-y divide-slate-100">@forelse ($recentMappings as $mapping)<div class="px-5 py-4 text-sm"><p class="font-mono text-xs text-slate-500">{{ $mapping->lkpm_project_code }}</p><p class="mt-1 font-bold text-ink">{{ $mapping->project->project_code }} · {{ $mapping->project->company->name }}</p><p class="mt-1 text-xs text-slate-500">oleh {{ $mapping->mappedBy->name }} · {{ $mapping->created_at->translatedFormat('d M Y H:i') }}</p></div>@empty<p class="px-5 py-10 text-sm text-slate-500">Belum ada pemetaan terverifikasi.</p>@endforelse</div>
        </div>
    </section>
</x-layouts.app>
