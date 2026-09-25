<x-layouts.tailwind-app title="Detail Pegawai">
    @php($mask = fn ($value) => $value ? '••••'.substr($value, -4) : '—')
    <div class="space-y-6">
        <x-page-header
            :title="$employee->name"
            :subtitle="$employee->position ?: 'Jabatan belum tercatat'"
            :kicker="$employee->source_label.' · '.($employee->is_active ? 'Aktif' : 'Tidak aktif')"
        >
            <x-slot:actions>
                <x-ui.button :href="route('employees.edit', $employee)">Ubah</x-ui.button>
                <a href="{{ route('employees.index') }}" class="rounded-lg bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/20 hover:bg-white/25">Kembali</a>
            </x-slot:actions>
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Sumber Data" :value="$employee->source_label" hint="Satu row dapat berasal dari lebih dari satu sumber" value-class="text-[var(--theme-content-accent)]" />
                <x-stat-item label="Status" :value="$employee->is_active ? 'Aktif' : 'Tidak aktif'" hint="Status kepegawaian di aplikasi" :value-class="$employee->is_active ? 'text-emerald-700' : 'text-rose-700'" />
                <x-stat-item label="Honor Tahun Aktif" :value="'Rp '.number_format($honors->sum('net_amount'), 0, ',', '.')" :hint="number_format($honors->count(), 0, ',', '.').' rincian honor'" value-class="text-amber-700" />
            </div>
        </x-page-header>

        <div class="grid gap-5 lg:grid-cols-3">
            <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-sm lg:col-span-2"><h2 class="font-bold text-[var(--ui-fg-strong)]">Identitas dan kepegawaian</h2><dl class="mt-4 grid gap-4 sm:grid-cols-2">@foreach ([['NIP',$employee->nip],['NUPTK',$employee->nuptk],['NIK',$mask($employee->nik)],['Jenis kelamin',$employee->gender],['Jenis PTK',$employee->staff_type],['Status pegawai',$employee->employment_status],['Jabatan',$employee->position],['NPWP',$mask($employee->npwp)]] as [$label,$value])<div><dt class="text-xs font-semibold uppercase text-[var(--ui-fg-muted)]">{{ $label }}</dt><dd class="mt-1 font-medium text-[var(--ui-fg)]">{{ $value ?: '—' }}</dd></div>@endforeach</dl></section>
            <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-5 shadow-sm"><h2 class="font-bold text-[var(--ui-fg-strong)]">Pembayaran & provenance</h2><dl class="mt-4 space-y-4"><div><dt class="text-xs font-semibold uppercase text-[var(--ui-fg-muted)]">Bank</dt><dd class="mt-1 font-medium">{{ $employee->bank_name ?: '—' }}</dd></div><div><dt class="text-xs font-semibold uppercase text-[var(--ui-fg-muted)]">Rekening</dt><dd class="mt-1 font-medium">{{ $mask($employee->bank_account) }}</dd></div><div class="rounded-xl bg-[var(--ui-surface-soft)] p-3 text-xs text-[var(--ui-fg-muted)]">Sumber: <b>{{ $employee->source_label }}</b>. @if($employee->operator_locked)Data ini sudah dikoreksi operator; sinkronisasi berikutnya hanya mengisi field yang masih kosong.@elseData dapat diperbarui dari sumber sampai operator melakukan koreksi manual.@endif</div></dl></section>
        </div>

        <section x-data="{ skOpen: true, skTab: '{{ array_key_first(\App\Models\EmployeeCertificate::kinds()) }}' }" class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-[var(--ui-line)] p-5">
                <div class="min-w-0">
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">SK Penugasan</h2>
                    <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Syarat bukti dukung honor: SK GTT/PTT, SK Pembagian Tugas, SK Penetapan, SK Pembina.</p>
                </div>
                <button type="button" x-on:click="skOpen = ! skOpen" :aria-expanded="skOpen" aria-label="Tampilkan atau sembunyikan panel SK"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-[var(--ui-line)] text-[var(--ui-fg-muted)] hover:text-[var(--ui-fg-strong)]">
                    <x-ui.icon name="chevron-down" size="sm" x-show="skOpen" />
                    <x-ui.icon name="chevron-right" size="sm" x-show="! skOpen" />
                </button>
            </div>
            <div x-show="skOpen">
                <div class="ui-tabs">
                    <div class="ui-tabs-list" role="tablist" aria-label="Jenis SK">
                        @foreach(\App\Models\EmployeeCertificate::kinds() as $kind => $kindLabel)
                            @php($tabCert = $employee->latestCertificate($kind))
                            <button type="button" role="tab" id="tab-sk-{{ $kind }}" aria-controls="panel-sk-{{ $kind }}"
                                x-on:click="skTab = '{{ $kind }}'"
                                :aria-selected="skTab === '{{ $kind }}'"
                                :class="skTab === '{{ $kind }}' ? 'ui-tab ui-tab-active' : 'ui-tab'">
                                <span class="ui-tab-icon"><x-ui.icon name="document" size="sm" /></span>
                                <span>{{ $kindLabel }}</span>
                                <span aria-hidden="true" class="h-2 w-2 shrink-0 rounded-full {{ $tabCert && $tabCert->isExpired() ? 'bg-rose-500' : ($tabCert && $tabCert->isExpiringSoon() ? 'bg-amber-500' : ($tabCert ? 'bg-emerald-500' : 'bg-slate-300')) }}"></span>
                            </button>
                        @endforeach
                    </div>
                </div>
                @foreach(\App\Models\EmployeeCertificate::kinds() as $kind => $kindLabel)
                    @php($cert = $employee->latestCertificate($kind))
                    <div x-show="skTab === '{{ $kind }}'" role="tabpanel" id="panel-sk-{{ $kind }}" aria-labelledby="tab-sk-{{ $kind }}" class="px-5 py-3.5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                @if($cert)
                                    <p class="mt-0.5 text-sm text-[var(--ui-fg-muted)]">{{ $cert->number ?: 'Tanpa nomor' }} · Terbit {{ $cert->issued_date?->translatedFormat('d F Y') ?: '—' }} · Berlaku s.d. {{ $cert->valid_until?->translatedFormat('d F Y') ?: 'tanpa batas' }}</p>
                                @else
                                    <p class="mt-0.5 text-sm text-[var(--ui-fg-muted)]">Belum tercatat.</p>
                                @endif
                            </div>
                            <div>
                                @if($cert && $cert->isExpired())
                                    <x-ui.badge variant="danger">Kedaluwarsa</x-ui.badge>
                                @elseif($cert && $cert->isExpiringSoon())
                                    <x-ui.badge variant="warning">Berakhir &lt;30 hari</x-ui.badge>
                                @elseif($cert)
                                    <x-ui.badge variant="success">Berlaku</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral">Belum ada</x-ui.badge>
                                @endif
                            </div>
                            @if($cert && $cert->file_path)
                                <p class="mt-1 text-sm">
                                    <x-ui.button variant="secondary" :href="route('employees.certificates.download', $cert)" class="text-xs">Unduh pindaian ({{ $cert->file_name ?: 'file SK' }})</x-ui.button>
                                </p>
                            @endif
                        </div>
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs font-bold text-[var(--theme-content-accent)]">{{ $cert ? 'Ubah / hapus' : 'Tambah SK' }}</summary>
                            <form method="POST" action="{{ $cert ? route('employees.certificates.update', $cert) : route('employees.certificates.store', $employee) }}" enctype="multipart/form-data" class="mt-2 grid gap-2 sm:grid-cols-2">
                                @csrf
                                @if($cert) @method('PUT') @endif
                                <input type="hidden" name="kind" value="{{ $kind }}">
                                <x-ui.field label="Nomor SK"><x-ui.input name="number" value="{{ old('number', $cert->number ?? '') }}" maxlength="120" /></x-ui.field>
                                <x-ui.field label="Tanggal terbit"><x-ui.input type="date" name="issued_date" value="{{ old('issued_date', $cert?->issued_date?->format('Y-m-d') ?? '') }}" /></x-ui.field>
                                <x-ui.field label="Berlaku s.d."><x-ui.input type="date" name="valid_until" value="{{ old('valid_until', $cert?->valid_until?->format('Y-m-d') ?? '') }}" /></x-ui.field>
                                <x-ui.field label="Catatan"><x-ui.input name="notes" value="{{ old('notes', $cert->notes ?? '') }}" maxlength="2000" /></x-ui.field>
                                <x-ui.field label="Pindaian SK (PDF/JPG/PNG, maks 10 MB)" hint="Tersimpan di folder dokumen: SK\Nama Pegawai"><x-ui.input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" /></x-ui.field>
                                <div class="sm:col-span-2"><x-ui.button type="submit" variant="secondary" class="text-xs">Simpan SK</x-ui.button></div>
                            </form>
                            @if($cert)
                                <form method="POST" action="{{ route('employees.certificates.destroy', $cert) }}" class="mt-2" data-confirm="Hapus {{ $kindLabel }} milik {{ $employee->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-rose-700 hover:text-rose-900">Hapus SK</button>
                                </form>
                            @endif
                        </details>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm"><div class="border-b border-[var(--ui-line)] p-5"><h2 class="font-bold text-[var(--ui-fg-strong)]">Riwayat honor tahun anggaran aktif</h2><p class="mt-1 text-sm text-[var(--ui-fg-muted)]">{{ $honors->count() }} rincian · bruto Rp {{ number_format($honors->sum('gross_amount'), 0, ',', '.') }} · pajak Rp {{ number_format($honors->sum('tax_amount'), 0, ',', '.') }} · diterima Rp {{ number_format($honors->sum('net_amount'), 0, ',', '.') }}</p></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-[var(--ui-surface-muted)] text-left text-xs uppercase text-[var(--ui-fg-muted)]"><tr><th class="px-4 py-3">Tanggal / Bukti</th><th class="px-4 py-3">Jabatan</th><th class="px-4 py-3 text-right">Bruto</th><th class="px-4 py-3 text-right">PPh 21</th><th class="px-4 py-3 text-right">Diterima</th></tr></thead><tbody class="divide-y divide-[var(--ui-line)]">@forelse($honors as $honor) @php($trx=$honor->item?->transaction)<tr class="odd:bg-[var(--ui-surface-base)] even:bg-[var(--ui-surface-soft)]"><td class="px-4 py-3"><a class="theme-text font-semibold" href="{{ $trx ? route('transactions.show',$trx->id) : '#' }}">{{ $trx?->sourceValue('no_bukti') ?: '—' }}</a><div class="text-xs text-[var(--ui-fg-muted)]">{{ $trx?->sourceCarbon()?->translatedFormat('d F Y') ?: '—' }}</div></td><td class="px-4 py-3">{{ $honor->position ?: '—' }}</td><td class="px-4 py-3 text-right">Rp {{ number_format($honor->gross_amount,0,',','.') }}</td><td class="px-4 py-3 text-right">Rp {{ number_format($honor->tax_amount,0,',','.') }}</td><td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($honor->net_amount,0,',','.') }}</td></tr>@empty<tr><td colspan="5" class="px-6 py-12 text-center text-[var(--ui-fg-muted)]">Belum digunakan pada honorarium tahun aktif.</td></tr>@endforelse</tbody></table></div></section>

        @if($employee->payload)<details class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-6 shadow-sm"><summary class="cursor-pointer font-bold">Payload sumber sinkronisasi</summary><div class="mt-4 space-y-3">@foreach($employee->payload as $source=>$value)<div class="rounded-lg bg-[var(--ui-surface-soft)] p-3"><p class="text-xs font-bold uppercase text-[var(--ui-fg-muted)]">{{ $source }}</p><pre class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap break-words text-xs">{{ is_array($value) ? json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : $value }}</pre></div>@endforeach</div></details>@endif

        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
            <h2 class="font-bold text-rose-900">Hapus pegawai</h2>
            <p class="mt-1 text-sm text-rose-700">Penghapusan berlaku untuk record aplikasi ini. Jika pegawai masih tersedia di ARKAS atau Dapodik, record dapat dibuat kembali ketika sumber tersebut disinkronkan.</p>
            <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="mt-3" data-confirm="Hapus pegawai {{ $employee->name }} dari master pegawai? Jika masih ada di ARKAS/Dapodik, data dapat muncul kembali setelah sinkronisasi.">
                @csrf @method('DELETE')
                <x-ui.button type="submit" variant="danger">Hapus pegawai</x-ui.button>
            </form>
        </section>
    </div>
</x-layouts.tailwind-app>
