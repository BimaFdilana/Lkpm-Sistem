<section class="mt-7 overflow-hidden rounded-3xl bg-ink p-6 text-white shadow-xl shadow-slate-900/10 sm:p-8">
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.16em] text-teal-300">Jadwal pengawasan aktif</p>
            <h3 class="mt-3 text-2xl font-bold tracking-tight">{{ $target?->quarter ?? 'Periode belum aktif' }} {{ $target?->year ?? now()->year }}</h3>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Jadwal berada paling atas agar keputusan pimpinan selalu mengikuti masa kegiatan dan masa pelaporan perusahaan.</p>
        </div>
        <span class="status-pill bg-white/10 text-teal-200">{{ $target?->is_active ? 'Periode aktif' : 'Periode belum diaktifkan' }}</span>
    </div>
    <div class="mt-7 grid gap-3 md:grid-cols-2">
        <article class="rounded-2xl bg-white/8 p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-300">Masa kegiatan PIC</p><p class="mt-2 text-lg font-bold">{{ $target?->activity_starts_at?->translatedFormat('d M Y') ?? 'Belum diatur' }} – {{ $target?->activity_ends_at?->translatedFormat('d M Y') ?? 'Belum diatur' }}</p><p class="mt-1 text-xs text-slate-300">Pendampingan, kontak, dan verifikasi perusahaan.</p></article>
        <article class="rounded-2xl bg-teal-400/15 p-5"><p class="text-xs font-bold uppercase tracking-wide text-teal-200">Masa pelaporan OSS</p><p class="mt-2 text-lg font-bold text-white">{{ $target?->reporting_starts_at?->translatedFormat('d M Y') ?? 'Belum diatur' }} – {{ $target?->reporting_ends_at?->translatedFormat('d M Y') ?? 'Belum diatur' }}</p><p class="mt-1 text-xs text-teal-100">Periode perusahaan menyampaikan LKPM melalui OSS.</p></article>
    </div>
</section>

<section class="mt-5 grid gap-4 xl:grid-cols-2" aria-label="Capaian target tahunan dan periode berjalan">
    <article class="relative rounded-2xl border border-rose-200 bg-rose-50 p-6" aria-labelledby="annual-progress-title">
        <div class="pr-12">
            <p class="text-xs font-bold uppercase tracking-[.14em] text-rose-700">Capaian target tahunan {{ $target?->year ?? now()->year }}</p>
            <h3 id="annual-progress-title" class="mt-2 text-lg font-bold text-rose-950">Target yang perlu dikejar (tahunan)</h3>
        </div>
        <p class="mt-5 text-sm font-semibold text-rose-800">Target investasi tahunan</p>
        <p class="mt-1 text-3xl font-black tracking-tight text-rose-950">Rp {{ number_format($annualTarget / 1000000000000, 2, ',', '.') }} T</p>
        <p class="mt-1 text-xs font-semibold text-rose-800">Rp {{ number_format($annualTarget, 0, ',', '.') }}</p>
        <div class="mt-5 rounded-xl bg-white/75 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-rose-800">Masih perlu dikejar</p>
            <p class="mt-1 text-2xl font-black text-rose-950">Rp {{ number_format($annualRemaining / 1000000000000, 2, ',', '.') }} T</p>
            <p class="mt-2 text-sm leading-6 text-rose-900">Realisasi tahunan valid: Rp {{ number_format($annualApprovedRealization / 1000000000000, 2, ',', '.') }} T.</p>
        </div>
        <div class="mt-5 flex justify-between gap-3 text-sm font-bold text-rose-900"><span>Capaian target tahunan</span><span>{{ number_format($annualProgress, 1, ',', '.') }}%</span></div>
        <div class="mt-2 h-3 overflow-hidden rounded-full bg-rose-100" role="progressbar" aria-label="Capaian target tahunan" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $annualProgress }}"><span class="block h-full rounded-full bg-rose-500" style="width: {{ $annualProgress }}%"></span></div>
        <details class="group" @if ($errors->has('annual_target') || $errors->has('year')) open @endif>
            <summary class="absolute right-5 top-5 grid size-10 list-none place-items-center rounded-xl border border-rose-200 bg-white text-rose-800 shadow-sm transition hover:bg-rose-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 [&::-webkit-details-marker]:hidden" aria-label="Edit target investasi tahunan" title="Edit target investasi tahunan">
                <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                <span class="sr-only">Edit target investasi tahunan</span>
            </summary>
            <form method="POST" action="{{ route('dashboard.annual-target.store') }}" class="mt-6 border-t border-rose-200 pt-5">
                @csrf
                <input type="hidden" name="year" value="{{ $target?->year ?? now()->year }}">
                <label class="grid gap-2 text-sm font-bold text-rose-950">Ubah target tahunan (Rp)
                    <div class="relative"><span class="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-sm font-bold text-slate-500">Rp</span><input class="w-full rounded-xl pl-11" type="text" name="annual_target" data-currency-input value="{{ old('annual_target', $annualTarget) }}" @error('annual_target') aria-invalid="true" @enderror required></div>
                </label>
                @error('annual_target')<p class="mt-2 text-xs font-semibold text-rose-800">{{ $message }}</p>@enderror
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach (['tw_1' => 'TW I', 'tw_2' => 'TW II', 'tw_3' => 'TW III', 'tw_4' => 'TW IV'] as $field => $quarter)
                        <label class="grid gap-1.5 text-xs font-bold text-rose-950">Alokasi {{ $quarter }} (Rp)
                            <input class="w-full rounded-xl" type="text" name="{{ $field }}" data-currency-input value="{{ old($field, $annualTargetDistribution[$quarter] ?? '') }}" required>
                        </label>
                        @error($field)<p class="text-xs font-semibold text-rose-800">{{ $message }}</p>@enderror
                    @endforeach
                </div>
                @unless ($annualTargetDistributionComplete)
                    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-semibold leading-5 text-amber-900">Pembagian target TW I–IV belum lengkap. Isi seluruh alokasi berdasarkan keputusan resmi sebelum menyimpan; sistem tidak membuat angka asumsi.</p>
                @endunless
                <p class="mt-3 text-xs leading-5 text-rose-800">Jumlah alokasi TW I–IV wajib sama dengan target tahunan. Target TW aktif dan periode tertutup tetap dipertahankan.</p>
                <button class="primary-action mt-4" type="submit">Simpan target tahunan</button>
            </form>
        </details>
    </article>

    <article class="surface-card p-6" aria-labelledby="quarter-progress-title">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="page-eyebrow">Capaian periode berjalan</p>
                <h3 id="quarter-progress-title" class="mt-2 text-lg font-bold text-ink">Capaian Periode Berjalan ({{ $target?->quarter ?? 'TW' }})</h3>
                <p class="mt-1 text-sm leading-6 text-slate-600">Hanya tambahan investasi dari LKPM berstatus Disetujui pada TW aktif yang mengisi progress bar ini.</p>
            </div>
            <span class="status-pill {{ $target?->is_active ? 'bg-teal-50 text-brand' : 'bg-slate-100 text-slate-600' }}">{{ $target?->is_active ? $target->quarter.' '.$target->year.' · aktif' : 'Belum ada TW aktif' }}</span>
        </div>
        @if ($target?->is_active)
            <div class="mt-5 grid gap-3 sm:grid-cols-3 xl:grid-cols-1 2xl:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Target TW aktif</p><p class="mt-2 break-words text-lg font-black text-ink" title="Rp {{ number_format($quarterTarget, 0, ',', '.') }}">{{ \App\Support\Rupiah::compact($quarterTarget) }}</p></div>
                <div class="rounded-xl bg-teal-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-brand">LKPM Disetujui TW ini</p><p class="mt-2 break-words text-lg font-black text-teal-950" title="Rp {{ number_format($validRealization, 0, ',', '.') }}">{{ \App\Support\Rupiah::compact($validRealization) }}</p></div>
                <div class="rounded-xl bg-rose-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-rose-800">Sisa target TW aktif</p><p class="mt-2 break-words text-lg font-black text-rose-950" title="Rp {{ number_format($targetGap, 0, ',', '.') }}">{{ \App\Support\Rupiah::compact($targetGap) }}</p></div>
            </div>
            <div class="mt-5 flex justify-between gap-3 text-sm font-bold text-slate-700"><span>Capaian target {{ $target->quarter }}</span><span>{{ number_format($targetProgress, 1, ',', '.') }}%</span></div>
            <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Capaian target {{ $target->quarter }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $targetProgress }}"><span class="block h-full rounded-full bg-brand transition-all" style="width: {{ $targetProgress }}%"></span></div>
            @if ($previousQuarterShortfalls->isNotEmpty())
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-bold text-amber-950">Sisa target TW sebelumnya yang tercatat</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        @foreach ($previousQuarterShortfalls as $shortfall)
                            <div class="rounded-lg bg-white/80 px-3 py-2 text-sm text-amber-950"><span class="font-bold">{{ $shortfall['quarter'] }}:</span> Rp {{ number_format($shortfall['remaining'], 0, ',', '.') }} <span class="block text-xs text-amber-800">Target Rp {{ number_format($shortfall['target'], 0, ',', '.') }} − LKPM Disetujui Rp {{ number_format($shortfall['realized'], 0, ',', '.') }}</span></div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs leading-5 text-amber-900">Rincian ini bersifat informatif dan tidak ditambahkan lagi ke target TW aktif, karena target periode yang ditetapkan dapat sudah memuat carry-over. Kekurangan antartahun tidak dibawa otomatis.</p>
                </div>
            @endif
            @if ($previousQuartersWithoutTargets->isNotEmpty())
                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="font-bold text-ink">Realisasi LKPM TW sebelumnya tersedia; alokasi target per TW belum lengkap</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        @foreach ($previousQuartersWithoutTargets as $period)
                            <p class="rounded-lg bg-white px-3 py-2"><span class="font-bold">{{ $period['quarter'] }}:</span> {{ \App\Support\Rupiah::compact($period['realized']) }} <span class="block text-xs text-slate-500">{{ number_format($period['reports'], 0, ',', '.') }} LKPM Disetujui · target TW belum tersimpan</span></p>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs leading-5 text-slate-600">Laporan yang sudah diimpor tetap dihitung. Sisa atau carry-over per TW belum dapat dihitung tanpa alokasi target TW tersebut; angka ini tidak ditambahkan lagi ke target TW aktif.</p>
                </div>
            @elseif ($previousQuarterShortfalls->isEmpty() && $target->quarter !== 'TW I')
                <p class="mt-5 rounded-xl bg-teal-50 p-4 text-sm text-teal-900">Tidak ada sisa target TW sebelumnya yang tercatat pada tahun ini.</p>
            @endif
        @else
            <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Aktifkan periode kerja untuk menampilkan progres target TW.</p>
        @endif
    </article>
</section>

<section class="surface-card mt-5 p-6">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <p class="page-eyebrow">Riwayat pelaporan LKPM</p>
            <h3 class="mt-2 text-lg font-bold text-ink">LKPM Disetujui TW I–IV</h3>
            <p class="mt-1 text-sm leading-6 text-slate-600">Jumlah laporan dibanding TW yang sama tahun sebelumnya; cakupan perusahaan dibanding basis non-UMK terdaftar.</p>
        </div>
        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <label class="grid gap-1 text-sm font-bold text-slate-700">Filter tahun
                <select name="lkpm_year" class="min-w-36 rounded-xl border-slate-300 text-sm" onchange="this.form.submit()">
                    <option value="all" @selected($lkpmFilterYear === 'all')>Semua tahun</option>
                    @foreach ($lkpmYears as $year)
                        <option value="{{ $year }}" @selected((string) $year === (string) $lkpmFilterYear)>{{ $year }}</option>
                    @endforeach
                </select>
            </label>
            <button class="secondary-action" type="submit">Tampilkan</button>
        </form>
    </div>
    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($lkpmQuarterSummaries as $summary)
            <article class="rounded-2xl border p-5 {{ $summary['has_reports'] ? 'border-teal-100 bg-teal-50' : 'border-slate-800 bg-slate-900 text-slate-200' }}" @if (! $summary['has_reports']) aria-label="LKPM TW {{ $summary['quarter'] }} {{ $summary['year_label'] }} belum tersedia" @endif>
                <p class="text-sm font-bold {{ $summary['has_reports'] ? 'text-brand' : 'text-slate-300' }}">LKPM Disetujui TW {{ $summary['quarter'] }}</p>
                @if ($summary['has_reports'])
                    <p class="mt-3 text-3xl font-black tracking-tight text-teal-950">{{ number_format($summary['approved_reports']) }} <span class="text-base font-bold">laporan</span></p>
                    <p class="mt-1 text-sm font-bold text-brand">dari {{ number_format($summary['reporting_companies']) }} perusahaan unik</p>
                    <p class="mt-2 text-xs font-semibold leading-5 text-teal-900">Acuan: {{ number_format($summary['eligible_projects']) }} proyek{{ $summary['all_years'] ? '-tahun Non-UMK' : ' Non-UMK aktif' }} <span class="font-normal">(indikasi laporan yang diharapkan: 1 per proyek per TW{{ $summary['all_years'] ? ' per tahun' : '' }})</span></p>
                    <p class="mt-1 text-xs leading-5 text-teal-800">{{ number_format($summary['reporting_projects']) }} dari {{ number_format($summary['eligible_projects']) }} {{ $summary['all_years'] ? 'proyek-tahun' : 'proyek' }} sudah memiliki LKPM Disetujui @if ($summary['project_coverage'] !== null)({{ number_format($summary['project_coverage'], 1, ',', '.') }}%)@endif</p>
                    <p class="mt-2 text-xs leading-5 text-teal-800">{{ $summary['all_years'] ? 'Semua tahun tersedia' : 'Tahun '.$lkpmFilterYear }} · status laporan Disetujui ·
                        @if ($summary['all_years'])
                            gabungan data lintas tahun; tidak dibandingkan dengan tahun sebelumnya.
                        @elseif ($summary['year_over_year'] === null)
                            perbandingan dengan {{ $lkpmFilterYear - 1 }} belum tersedia.
                        @elseif ($summary['year_over_year'] > 0)
                            meningkat {{ number_format($summary['year_over_year'], 1, ',', '.') }}% dari {{ $lkpmFilterYear - 1 }}.
                        @elseif ($summary['year_over_year'] < 0)
                            menurun {{ number_format(abs($summary['year_over_year']), 1, ',', '.') }}% dari {{ $lkpmFilterYear - 1 }}.
                        @else
                            sama dengan {{ $lkpmFilterYear - 1 }}.
                        @endif
                    </p>
                    <p class="mt-3 border-t border-teal-200 pt-3 text-xs font-semibold leading-5 text-teal-950">{{ number_format($summary['reporting_companies']) }} dari {{ number_format($summary['eligible_companies']) }} {{ $summary['all_years'] ? 'perusahaan-tahun non-UMK' : 'perusahaan non-UMK terdaftar' }} @if ($summary['company_coverage'] !== null)({{ number_format($summary['company_coverage'], 1, ',', '.') }}%)@endif</p>
                @else
                    <p class="mt-3 text-3xl font-black tracking-tight text-white">—</p>
                    <p class="mt-2 text-xs leading-5 text-slate-300">{{ $summary['all_years'] ? 'Tidak ada data pada tahun yang tersedia' : 'Tahun '.$lkpmFilterYear }} · belum ada data TW {{ $summary['quarter'] }}. Kartu belum aktif.</p>
                    <p class="mt-3 border-t border-slate-700 pt-3 text-xs font-semibold text-slate-300">Menunggu data LKPM untuk triwulan ini</p>
                @endif
            </article>
        @endforeach
    </div>
    <p class="mt-4 text-xs leading-5 text-slate-500">Angka “laporan” adalah jumlah baris LKPM berstatus Disetujui; satu proyek dapat memiliki lebih dari satu laporan/baris. Acuan “laporan yang diharapkan” menggunakan jumlah proyek Non-UMK aktif saat ini di DP.Proyek (asumsi indikatif 1 laporan per proyek per TW), bukan daftar kewajiban historis final. Pada filter Semua tahun, cakupan dihitung sebagai proyek-tahun/perusahaan-tahun dengan jumlah proyek aktif saat ini dikalikan jumlah tahun data tersedia; ini hanya estimasi karena daftar proyek historis dapat berubah. Baris laporan yang belum tertaut proyek tetap dihitung pada jumlah laporan, tetapi tidak menambah cakupan proyek/perusahaan. Satu perusahaan juga dapat memiliki beberapa proyek/KBLI, sehingga cakupan proyek dan perusahaan unik ditampilkan terpisah.</p>
</section>

<section class="surface-card mt-5 p-6">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="page-eyebrow">Kinerja tindak lanjut PIC</p><h3 class="mt-2 text-lg font-bold text-ink">Status kerja tim pada {{ $target?->quarter ?? 'periode aktif' }}</h3></div><span class="status-pill bg-teal-50 text-brand">Operasional PIC</span></div>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Assignment aktif</p><p class="mt-2 text-2xl font-black text-ink">{{ number_format($assignments) }}</p><p class="mt-1 text-xs text-slate-500">perusahaan menjadi tugas PIC</p></article>
        <article class="rounded-2xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sudah dikontak</p><p class="mt-2 text-2xl font-black text-ink">{{ number_format($contactedCompanies) }}</p><p class="mt-1 text-xs text-slate-500">hasil kontak dicatat PIC</p></article>
        <article class="rounded-2xl bg-teal-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-brand">Konfirmasi PIC</p><p class="mt-2 text-2xl font-black text-teal-950">{{ number_format($confirmedCompanies) }}</p><p class="mt-1 text-xs text-teal-800">perusahaan memberi indikasi</p></article>
        <article class="rounded-2xl bg-amber-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-amber-800">Nilai indikatif</p><p class="mt-2 text-2xl font-black text-amber-950">Rp {{ number_format($indicatedAmount / 1000000000, 1, ',', '.') }} M</p><p class="mt-1 text-xs text-amber-900">bukan realisasi resmi LKPM</p></article>
    </div>
</section>

<section class="mt-5 rounded-2xl border border-teal-200 bg-teal-50 p-5 text-sm leading-6 text-teal-950"><p class="font-bold">Aturan pembacaan dashboard</p><p class="mt-1">Capaian target hanya berasal dari LKPM Disetujui. Nilai indikatif PIC membantu monitoring tindak lanjut, tetapi tidak menambah realisasi investasi sebelum laporan OSS disetujui.</p></section>
