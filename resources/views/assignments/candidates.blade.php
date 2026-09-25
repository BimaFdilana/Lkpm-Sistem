<section id="candidates" class="mt-10 scroll-mt-24 space-y-5" aria-labelledby="candidates-title">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="page-eyebrow">Candidates · {{ $period ? $period->quarter.' '.$period->year : 'Periode belum aktif' }} · Snapshot {{ $candidateSnapshotDate ? \Carbon\Carbon::parse($candidateSnapshotDate)->translatedFormat('d M Y') : 'belum tersedia' }}</p>
                <h2 id="candidates-title" class="page-title">Candidates</h2>
                <p class="page-copy">Semua perusahaan pada snapshot ditampilkan. Riwayat LKPM Disetujui empat TW sebelumnya, aktivitas dua TW terakhir, proyeksi, lalu sisa potensi menentukan urutan. Urutan adalah rekomendasi kerja, bukan jaminan perusahaan akan melapor.</p>
            </div>
            <span class="status-pill bg-teal-50 text-brand">{{ number_format($candidateCompanies->total()) }} perusahaan</span>
        </div>

        @if ($candidateSnapshotDate && ! $candidateHasAssignments && $candidateEligibleCount > 0)
            <form method="POST" action="{{ route('assignments.candidates.assign') }}" class="surface-card flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center">
                @csrf
                <div>
                    <p class="font-bold text-ink">Pembagian awal: {{ number_format(min(100, $candidateEligibleCount)) }} perusahaan total</p>
                    <p class="mt-1 text-sm text-slate-600">Dibagi merata ke seluruh PIC dengan metode ular. Setelah PIC mencatat hasil kontak, antrean otomatis memberi perusahaan berikutnya kepada PIC tersebut.</p>
                </div>
                <button type="submit" class="primary-action shrink-0">Bagikan tugas ke PIC</button>
            </form>
        @elseif ($candidateHasAssignments)
            <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">Tugas awal periode ini sudah dibagikan. Riwayat assignment dipertahankan; perusahaan yang selesai dihubungi diganti otomatis dari antrean berikutnya.</div>
        @else
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">Belum ada snapshot atau perusahaan dengan sisa potensi. Buat snapshot harian dari Dashboard Kabag setelah data diperbarui.</div>
        @endif

        <section class="surface-card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-bold text-ink">Seluruh perusahaan menurut prioritas</h3>
                <p class="mt-1 text-xs text-slate-500">Perusahaan tanpa riwayat tetap terlihat. Gap untuk verifikasi tidak dihitung sebagai proyeksi atau realisasi resmi.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr><th class="px-5 py-3">Urutan</th><th class="px-5 py-3">Perusahaan</th><th class="px-5 py-3">Aktivitas LKPM</th><th class="px-5 py-3">Status antrean</th><th class="px-5 py-3 text-right">Proyeksi TW</th><th class="px-5 py-3 text-right">Sisa potensi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($candidateCompanies as $company)
                            <tr class="hover:bg-teal-50/40">
                                <td class="px-5 py-4 font-black text-amber-800">#{{ $company->priority_rank }}</td>
                                <td class="px-5 py-4"><p class="font-bold text-ink">{{ $company->name }}</p><p class="mt-1 text-xs text-slate-500">NIB {{ $company->nib }} · {{ $company->project_count }} proyek</p></td>
                                <td class="px-5 py-4"><p class="font-semibold text-ink">{{ $company->activity_quarters }}/4 TW sebelumnya</p><p class="mt-1 text-xs text-slate-500">{{ $company->last_two_quarters }}/2 TW terakhir</p></td>
                                <td class="px-5 py-4">
                                    @if (in_array($company->id, $candidateAssignedIds, true)) <span class="status-pill bg-teal-50 text-brand">Sudah dibagikan</span>
                                    @elseif (! $company->assignable) <span class="status-pill bg-slate-100 text-slate-600">Sudah lapor/selesai atau tanpa sisa</span>
                                    @elseif ($company->needs_verification) <span class="status-pill bg-amber-50 text-amber-800">Menunggu verifikasi</span>
                                    @else <span class="status-pill bg-slate-100 text-slate-700">Menunggu giliran</span> @endif
                                </td>
                                <td class="px-5 py-4 text-right font-semibold text-ink">{{ $company->needs_verification ? 'Belum ada' : 'Rp '.number_format($company->projected_contribution, 0, ',', '.') }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-ink">Rp {{ number_format($company->remaining_potential, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">Belum ada perusahaan pada snapshot periode aktif.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        {{ $candidateCompanies->links() }}
</section>
