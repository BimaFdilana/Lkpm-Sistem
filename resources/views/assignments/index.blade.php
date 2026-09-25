<x-layouts.app :title="$isPic ? 'Tugas Saya' : 'Assignment PIC'">
    <section class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="page-eyebrow">{{ $period ? $period->quarter.' '.$period->year : 'Periode belum aktif' }}</p>
            <h2 class="page-title">{{ $isPic ? 'Tugas Saya' : 'Assignment PIC' }}</h2>
            <p class="page-copy">
                {{ $isPic
                    ? 'Lihat perusahaan yang perlu dihubungi dan catatan tindak lanjut yang sudah Anda buat.'
                    : 'Pembagian awal maksimal 100 perusahaan total, lalu antrean prioritas mengisi tugas PIC setelah tindak lanjut. Riwayat tugas tetap tersimpan per TW.' }}
            </p>
        </div>
    </section>

    @if ($isPic)
        <nav class="surface-card mt-7 flex flex-wrap gap-2 p-2" aria-label="Daftar tugas PIC">
            <a href="{{ route('assignments.index') }}" @if ($tab === 'tasks') aria-current="page" @endif
                class="flex min-w-0 flex-1 items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm font-bold transition sm:flex-none {{ $tab === 'tasks' ? 'bg-brand text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-ink' }}">
                <span>Tugas</span>
                <span class="rounded-full px-2 py-0.5 text-xs {{ $tab === 'tasks' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">{{ number_format($taskCount) }}</span>
            </a>
            <a href="{{ route('assignments.index', ['tab' => 'history']) }}" @if ($tab === 'history') aria-current="page" @endif
                class="flex min-w-0 flex-1 items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm font-bold transition sm:flex-none {{ $tab === 'history' ? 'bg-brand text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-ink' }}">
                <span>Riwayat Tindak Lanjut</span>
                <span class="rounded-full px-2 py-0.5 text-xs {{ $tab === 'history' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">{{ number_format($historyCount) }}</span>
            </a>
        </nav>
    @endif

    <section class="surface-card {{ $isPic ? 'mt-4' : 'mt-7' }} overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <div>
                <h3 class="font-bold text-ink">{{ $isPic ? ($tab === 'history' ? 'Perusahaan yang sudah ditindaklanjuti' : 'Perusahaan dalam daftar tugas') : 'Perusahaan yang sudah dibagikan ke PIC' }}</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    @if ($isPic && $tab === 'history')
                        Catatan tindak lanjut Anda pada periode aktif. Buka detail untuk melihat riwayat lengkap atau mencatat perkembangan berikutnya.
                    @elseif ($isPic)
                        Perusahaan yang belum selesai Anda hubungi. Catatan “belum dihubungi” tidak menutup tugas; setelah hasil kontak dicatat, perusahaan berpindah ke Riwayat dan pengganti masuk otomatis jika masih ada antrean.
                    @else
                        Seluruh tugas aktif diambil dari antrean prioritas. Verifikasi awal tanpa dasar proyeksi tidak menambah capaian target.
                    @endif
                </p>
            </div>
            <span class="status-pill bg-teal-50 text-brand">{{ number_format($assignments->total()) }} perusahaan</span>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50/80 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5">Prioritas & perusahaan</th>
                        @unless ($isPic)<th class="px-5 py-3.5">PIC</th>@endunless
                        <th class="px-5 py-3.5">{{ $isPic && $tab === 'history' ? 'Tindak lanjut terakhir' : 'Status tugas' }}</th>
                        <th class="px-5 py-3.5">Proyek & sektor</th>
                        <th class="px-5 py-3.5"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assignments as $assignment)
                        @php($latestFollowUp = $assignment->followUps->first())
                        @php($queueLabel = match ($assignment->queue_type) { 'primary' => 'Sasaran utama', 'reserve' => 'Kandidat cadangan', default => 'Verifikasi awal Amber' })
                        <tr class="transition hover:bg-teal-50/40">
                            <td class="px-5 py-4">
                                <div class="flex gap-3">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $assignment->queue_type === 'primary' ? 'bg-amber-100 text-amber-800' : ($assignment->queue_type === 'verification' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }} text-xs font-black">#{{ $assignment->priority_rank }}</span>
                                    <div>
                                        <p class="font-bold text-ink">{{ $assignment->company->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">NIB {{ $assignment->company->nib }} · {{ $assignment->company->district ?: 'Lokasi belum ada' }}</p>
                                        <p class="mt-2 text-xs text-slate-600">{{ $queueLabel }} @if ($assignment->queue_type === 'verification')· Belum dihitung sebagai proyeksi target @else · Proyeksi Rp {{ number_format($assignment->potential_at_assignment, 0, ',', '.') }} @endif</p>
                                    </div>
                                </div>
                            </td>
                            @unless ($isPic)<td class="px-5 py-4 font-semibold text-slate-700">{{ $assignment->pic->name }}</td>@endunless
                            <td class="px-5 py-4">
                                @if ($isPic && $tab === 'history')
                                    <p class="font-semibold text-ink">{{ str($latestFollowUp?->contact_status ?? 'belum_dihubungi')->replace('_', ' ')->title() }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $latestFollowUp?->created_at?->translatedFormat('d M Y H:i') }}</p>
                                    <p class="mt-2 max-w-xs text-xs leading-5 text-slate-600">{{ $latestFollowUp?->note ? str($latestFollowUp->note)->limit(90) : 'Tanpa catatan tambahan' }}</p>
                                @else
                                    <span class="status-pill {{ $assignment->is_task_active ? 'bg-teal-50 text-brand' : 'bg-slate-100 text-slate-600' }}">{{ $assignment->is_task_active ? 'Tugas aktif' : 'Standby' }}</span>
                                    <p class="mt-2 text-xs font-medium text-slate-600">Verifikasi: {{ str($latestFollowUp?->verification_status ?? 'belum')->replace('_', ' ')->title() }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-ink">{{ $assignment->company->projects->count() }} proyek</p>
                                <p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">{{ $assignment->company->projects->pluck('sector')->filter()->unique()->join(', ') ?: 'Sektor belum dipetakan' }}</p>
                            </td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('assignments.show', $assignment) }}" class="text-sm font-bold text-brand hover:underline">{{ $isPic && $tab === 'history' ? 'Lihat riwayat' : 'Buka detail' }} →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isPic ? 4 : 5 }}" class="px-5 py-12 text-center text-slate-500">{{ $isPic ? ($tab === 'history' ? 'Belum ada perusahaan yang Anda tindak lanjuti pada periode ini.' : 'Belum ada perusahaan baru dalam daftar tugas Anda.') : 'Belum ada assignment. Gunakan tombol di bagian Candidates di bawah untuk membagikan tugas.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid divide-y divide-slate-100 md:hidden">
            @forelse ($assignments as $assignment)
                @php($latestFollowUp = $assignment->followUps->first())
                <article class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><p class="font-bold text-ink">{{ $assignment->company->name }}</p><p class="mt-1 text-xs text-slate-500">NIB {{ $assignment->company->nib }}</p></div>
                        <span class="status-pill shrink-0 {{ $assignment->is_task_active ? 'bg-teal-50 text-brand' : 'bg-slate-100 text-slate-600' }}">{{ $assignment->is_task_active ? 'Aktif' : 'Standby' }}</span>
                    </div>
                    @if ($isPic && $tab === 'history')
                        <p class="mt-3 text-xs font-semibold text-slate-700">{{ str($latestFollowUp?->contact_status ?? 'belum_dihubungi')->replace('_', ' ')->title() }} · {{ $latestFollowUp?->created_at?->translatedFormat('d M Y H:i') }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-600">{{ $latestFollowUp?->note ? str($latestFollowUp->note)->limit(110) : 'Tanpa catatan tambahan' }}</p>
                    @else
                        <p class="mt-3 text-xs text-slate-600">Verifikasi: {{ str($latestFollowUp?->verification_status ?? 'belum')->replace('_', ' ')->title() }}</p>
                    @endif
                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
                        @unless ($isPic)<div class="rounded-xl bg-slate-50 p-3"><p class="text-slate-500">PIC</p><p class="mt-1 font-bold text-ink">{{ $assignment->pic->name }}</p></div>@endunless
                        <div class="rounded-xl bg-slate-50 p-3"><p class="text-slate-500">Proyek</p><p class="mt-1 font-bold text-ink">{{ $assignment->company->projects->count() }} proyek</p></div>
                    </div>
                    <a href="{{ route('assignments.show', $assignment) }}" class="mt-4 inline-flex text-sm font-bold text-brand">{{ $isPic && $tab === 'history' ? 'Lihat riwayat' : 'Lihat detail' }} →</a>
                </article>
            @empty
                <p class="px-5 py-12 text-center text-sm text-slate-500">{{ $isPic ? ($tab === 'history' ? 'Belum ada perusahaan yang Anda tindak lanjuti pada periode ini.' : 'Belum ada perusahaan baru dalam daftar tugas Anda.') : 'Belum ada assignment aktif.' }}</p>
            @endforelse
        </div>
    </section>
    <div class="mt-5">{{ $assignments->links() }}</div>
    @unless ($isPic)
        @include('assignments.candidates')
    @endunless
</x-layouts.app>
