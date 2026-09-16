<x-layouts.app title="Detail Perusahaan · Assignment PIC">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('assignments.index') }}" class="text-sm font-medium text-brand hover:underline">← Kembali ke assignment</a>
            <p class="mt-4 text-sm font-medium text-brand">{{ $assignment->year }} · {{ $assignment->quarter }}</p>
            <h2 class="mt-1 text-3xl font-semibold text-ink">{{ $assignment->company->name }}</h2>
            <p class="mt-2 text-slate-600">NIB {{ $assignment->company->nib }} · PIC {{ $assignment->pic->name }}</p>
            <span class="status-pill mt-3 {{ $assignment->is_task_active ? 'bg-teal-50 text-brand' : 'bg-slate-100 text-slate-600' }}">{{ $assignment->is_task_active ? 'Tugas aktif' : 'Cadangan standby' }}</span>
            @php($currentVerification = $assignment->followUps->sortByDesc('created_at')->first()?->verification_status ?? 'belum')
            <span class="status-pill mt-3 bg-amber-50 text-amber-800">Verifikasi: {{ str($currentVerification)->replace('_', ' ')->title() }}</span>
        </div>

        @if (auth()->user()->role === 'kepala_bagian')
            <form method="POST" action="{{ route('assignments.pic.update', $assignment) }}" class="flex flex-wrap items-end gap-2 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                @csrf
                @method('PUT')
                <label class="grid gap-1 text-xs font-medium text-slate-600">PIC penanggung jawab
                    <select name="pic_id" class="rounded-lg border-slate-300 text-sm">
                        @foreach ($pics as $pic)
                            <option value="{{ $pic->id }}" @selected($pic->id === $assignment->pic_id)>{{ $pic->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white">Simpan PIC</button>
            </form>
        @endif
    </div>

    <section class="mt-7 grid gap-5 lg:grid-cols-[1.1fr_.9fr]">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="font-semibold text-ink">Kontak dan alamat perusahaan</h3>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2 text-sm">
                <div><dt class="text-slate-500">Alamat</dt><dd class="mt-1 font-medium">{{ $assignment->company->address ?: 'Belum tersedia' }}</dd></div>
                <div><dt class="text-slate-500">Lokasi</dt><dd class="mt-1 font-medium">{{ collect([$assignment->company->district, $assignment->company->subdistrict])->filter()->join(', ') ?: 'Belum tersedia' }}</dd></div>
                <div><dt class="text-slate-500">Nama kontak</dt><dd class="mt-1 font-medium">{{ $assignment->company->contact_name ?: 'Belum tersedia' }}</dd></div>
                <div><dt class="text-slate-500">Telepon</dt><dd class="mt-1 font-medium">{{ $assignment->company->contact_phone ?: 'Belum tersedia' }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd class="mt-1 font-medium">{{ $assignment->company->contact_email ?: 'Belum tersedia' }}</dd></div>
                <div><dt class="text-slate-500">Jabatan kontak</dt><dd class="mt-1 font-medium">{{ $assignment->company->contact_position ?: 'Belum tersedia' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6">
            <h3 class="font-semibold text-amber-950">Catatan verifikasi data proyek</h3>
            <p class="mt-2 text-sm leading-6 text-amber-900">Jika proyek belum memiliki riwayat LKPM yang terhubung, realisasi operasional ditampilkan Rp0 sementara dan wajib dikonfirmasi kepada perusahaan atau melalui verifikasi lapangan. Status verifikasi PIC di bawah ini tidak mengubah status LKPM OSS.</p>
        </div>
    </section>

    <section class="mt-7 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-200 px-6 py-4"><h3 class="font-semibold text-ink">Proyek, realisasi, dan gap potensi</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Proyek / KBLI</th><th class="px-5 py-3">Rencana</th><th class="px-5 py-3">Akumulasi</th><th class="px-5 py-3">Gap potensi</th><th class="px-5 py-3">Status LKPM</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($projectRows as $row)
                        <tr>
                            <td class="px-5 py-4"><p class="font-semibold text-ink">{{ $row['project']->name ?: $row['project']->project_code }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['project']->project_code }} · {{ $row['project']->kbli }} {{ $row['project']->kbli_description }}</p></td>
                            <td class="px-5 py-4">Rp {{ number_format($row['project']->planned_investment, 0, ',', '.') }}</td>
                            <td class="px-5 py-4">Rp {{ number_format($row['report']?->accumulated_investment ?? 0, 0, ',', '.') }}</td>
                            <td class="px-5 py-4 font-medium text-amber-800">Rp {{ number_format($row['potential'], 0, ',', '.') }}</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs">{{ $row['report']?->report_status ?? 'Perlu verifikasi' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if (auth()->user()->role === 'pic' && $assignment->is_task_active)
        <section class="mt-7 grid gap-5 lg:grid-cols-[.85fr_1.15fr]">
            <form method="POST" action="{{ route('assignments.follow-ups.store', $assignment) }}" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @csrf
                <h3 class="font-semibold text-ink">Catat tindak lanjut</h3>
                <div class="mt-5 grid gap-4">
                    <label class="grid gap-1 text-sm font-medium">Status kontak
                        <select name="contact_status" class="rounded-lg border-slate-300"><option value="belum_dihubungi">Belum dihubungi</option><option value="sudah_dihubungi">Sudah dihubungi</option><option value="tidak_dapat_dihubungi">Tidak dapat dihubungi</option></select>
                    </label>
                    <label class="grid gap-1 text-sm font-medium">Status konfirmasi
                        <select name="confirmation_status" class="rounded-lg border-slate-300"><option value="belum_terkonfirmasi">Belum terkonfirmasi</option><option value="terkonfirmasi">Terkonfirmasi</option><option value="terkonfirmasi_sebagian">Terkonfirmasi sebagian</option><option value="nilai_kurang">Nilai kurang</option><option value="tidak_sesuai">Tidak sesuai</option></select>
                    </label>
                    <label class="grid gap-1 text-sm font-medium">Status verifikasi
                        <select name="verification_status" class="rounded-lg border-slate-300"><option value="belum">Belum diverifikasi</option><option value="dijadwalkan">Dijadwalkan</option><option value="dikunjungi">Dikunjungi</option><option value="terverifikasi">Terverifikasi</option><option value="selesai">Selesai</option><option value="tidak_dapat_dihubungi">Tidak dapat dihubungi</option></select>
                    </label>
                    <label class="grid gap-1 text-sm font-medium">Nilai indikatif konfirmasi<div class="relative"><span class="pointer-events-none absolute inset-y-0 left-0 grid w-11 place-items-center text-sm font-bold text-slate-500">Rp</span><input name="indicated_amount" type="text" inputmode="numeric" data-currency-input class="w-full rounded-lg border-slate-300 pl-11" placeholder="Bukan realisasi resmi LKPM"></div></label>
                    <label class="grid gap-1 text-sm font-medium">Catatan<textarea name="note" rows="4" class="rounded-lg border-slate-300"></textarea></label>
                    <label class="grid gap-1 text-sm font-medium">Tindak lanjut berikutnya<input name="next_follow_up_at" type="date" class="rounded-lg border-slate-300"></label>
                    <button class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white">Simpan tindak lanjut</button>
                </div>
            </form>
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><h3 class="font-semibold text-ink">Riwayat tindak lanjut</h3><div class="mt-5 grid gap-4">@forelse ($assignment->followUps->sortByDesc('created_at') as $followUp)<article class="rounded-lg border border-slate-200 p-4"><div class="flex justify-between gap-3"><div><p class="font-medium text-ink">Kontak: {{ str_replace('_', ' ', $followUp->contact_status) }}</p><p class="mt-1 text-xs text-slate-500">Verifikasi: {{ str_replace('_', ' ', $followUp->verification_status) }} · Konfirmasi: {{ str_replace('_', ' ', $followUp->confirmation_status) }}@if($followUp->indicated_amount !== null) · Rp {{ number_format($followUp->indicated_amount, 0, ',', '.') }}@endif</p></div><span class="text-xs text-slate-500">{{ $followUp->created_at->format('d M Y H:i') }}</span></div><p class="mt-2 text-sm text-slate-700">{{ $followUp->note ?: 'Tanpa catatan' }}</p></article>@empty<p class="text-sm text-slate-500">Belum ada tindak lanjut.</p>@endforelse</div></div>
        </section>
    @elseif (auth()->user()->role === 'pic')
        <section class="mt-7 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"><p class="font-bold">Kandidat cadangan masih standby.</p><p class="mt-1">Perusahaan ini akan menjadi tugas aktif secara otomatis bila hasil konfirmasi tim menunjukkan proyeksi masih kurang dari buffer periode aktif.</p></section>
    @endif

    <section class="mt-7 grid gap-4">
        @foreach ($projectRows as $row)
            <details class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <summary class="cursor-pointer font-semibold text-ink">Seluruh data sumber · {{ $row['project']->project_code }}</summary>
                <h4 class="mt-6 text-sm font-semibold text-slate-700">DP.Proyek</h4>
                <dl class="mt-3 grid gap-x-8 gap-y-3 sm:grid-cols-2 text-sm">@foreach ($row['project']->source_payload ?? [] as $field => $value)<div><dt class="text-slate-500">{{ $field }}</dt><dd class="mt-1 break-words font-medium">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value ?: '-') }}</dd></div>@endforeach</dl>
                @if ($row['report'])
                    <h4 class="mt-8 text-sm font-semibold text-slate-700">Laporan LKPM</h4>
                    <dl class="mt-3 grid gap-x-8 gap-y-3 sm:grid-cols-2 text-sm">@foreach ($row['report']->source_payload ?? [] as $field => $value)<div><dt class="text-slate-500">{{ $field }}</dt><dd class="mt-1 break-words font-medium">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value ?: '-') }}</dd></div>@endforeach</dl>
                @endif
            </details>
        @endforeach
    </section>
</x-layouts.app>
