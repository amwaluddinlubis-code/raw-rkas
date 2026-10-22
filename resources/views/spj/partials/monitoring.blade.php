{{-- Tab Monitoring: ringkasan tindak lanjut, aksi periode (bulk final, penomoran triwulan, status periode), antrean transaksi tertunda. --}}
                <div class="space-y-4 bg-[var(--ui-surface-soft)] p-4 sm:p-6">
                    <section class="flex flex-col gap-3 rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[.14em] text-[var(--theme-content-accent)]">Ruang kontrol SPJ</p>
                            <h2 class="mt-1 text-lg font-bold text-[var(--ui-fg-strong)]">Monitoring dan penutupan periode</h2>
                            <p class="mt-1 text-sm text-[var(--ui-fg-muted)]">Transaksi ber-rincian yang belum siap atau belum bernomor.</p>
                        </div>
                        <div class="rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-soft)] px-4 py-3 sm:min-w-36 sm:text-right">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Perlu ditindaklanjuti</p>
                            <p class="mt-1 text-2xl font-extrabold text-[var(--theme-content-accent)]">{{ number_format($pendingPaginator?->total() ?? 0, 0, ',', '.') }}</p>
                            <p class="text-xs text-[var(--ui-fg-muted)]">transaksi</p>
                        </div>
                    </section>
                    @if(auth()->user()?->isAdministrator())
                        <div class="grid gap-4 xl:grid-cols-2">
                        <form method="POST" action="{{ route('spj.bulk-finalize') }}" class="rounded-xl border border-emerald-200 bg-[var(--ui-surface-base)] p-4 shadow-sm" data-confirm="Finalkan semua paket NUMBERED pada triwulan terpilih? Proses ini membuat snapshot dan mengunci paket.">
                            @csrf
                            <div class="mb-3"><h3 class="font-bold text-[var(--ui-fg-strong)]">Bulk Final SPJ</h3><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Kunci seluruh paket NUMBERED setelah dokumen dan snapshot siap.</p></div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end"><x-ui.field label="Triwulan" class="flex-1"><x-ui.select name="quarter">@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}">Triwulan {{ $quarter }}</option>@endforeach</x-ui.select></x-ui.field><x-ui.button type="submit" variant="success" class="shrink-0">Finalkan paket</x-ui.button></div>
                            <p class="basis-full text-xs text-[var(--ui-fg-muted)]">Hanya paket NUMBERED pada triwulan dan sumber dana aktif yang diproses. Jika ada paket gagal, seluruh batch dibatalkan.</p>
                        </form>
                        <form method="POST" action="{{ route('spj.quarter-numbering') }}" class="rounded-xl border border-indigo-200 bg-[var(--ui-surface-base)] p-4 shadow-sm" data-confirm="Rekonsiliasi nomor triwulan ini? Transaksi yang sudah memiliki nomor aktif akan dilewati dan slot nomor yang dibatalkan dapat dipakai dokumen berikutnya dalam domain serta periode yang sama.">
                            @csrf
                            <div class="mb-3"><h3 class="font-bold text-[var(--ui-fg-strong)]">Penomoran Triwulan</h3><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Tetapkan nomor dokumen untuk paket berstatus READY.</p></div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end"><x-ui.field label="Triwulan" class="flex-1"><x-ui.select name="quarter">@foreach(range(1,4) as $quarter)<option value="{{ $quarter }}">Triwulan {{ $quarter }}</option>@endforeach</x-ui.select></x-ui.field><x-ui.button type="submit" class="shrink-0">Tetapkan nomor</x-ui.button></div>
                            <p class="basis-full text-xs text-[var(--ui-fg-muted)]">Nomor aktif dipertahankan. Slot nomor batal dipakai kembali menurut urutan terkecil oleh dokumen berikutnya dalam jenis dan periode penomoran yang sama.</p>
                            <p class="basis-full text-xs text-[var(--ui-fg-muted)]">Setiap jenis dokumen diurutkan menurut tanggal peristiwanya. Nomor yang sudah terbit akan dilewati.</p>
                        </form>
                        </div>
                        <section class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm sm:p-5">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><h3 class="font-bold text-[var(--ui-fg-strong)]">Status periode</h3><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Tutup triwulan hanya setelah seluruh paket berstatus FINAL.</p></div><span class="text-xs font-semibold text-[var(--ui-fg-muted)]">Tahun anggaran aktif</span></div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach(range(1,4) as $quarter)
                                @php
                                    $period = ($periodClosures ?? collect())->get($quarter);
                                @endphp
                                <div class="rounded-lg border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-3"><div class="flex items-center justify-between"><b class="text-sm text-[var(--ui-fg-strong)]">Triwulan {{ $quarter }}</b><span class="rounded-full bg-[var(--ui-surface-muted)] px-2 py-1 text-xs font-bold text-[var(--ui-fg-muted)]">{{ $period?->status ?? 'OPEN' }}</span></div>
                                    @if($period?->status === 'NUMBERED')<form method="POST" action="{{ route('spj.quarter-close') }}" class="mt-2">@csrf<input type="hidden" name="quarter" value="{{ $quarter }}"><x-ui.button type="submit" variant="secondary" class="w-full px-3 py-1.5 text-xs">Tutup triwulan</x-ui.button></form>@endif
                                    @if($period?->status === 'CLOSED')<form method="POST" action="{{ route('spj.quarter-reopen', $period->id) }}" class="mt-2 space-y-2">@csrf<x-ui.input name="reason" required placeholder="Alasan pembukaan" class="text-xs" /><x-ui.button type="submit" variant="warning" class="w-full px-3 py-1.5 text-xs">Buka kembali</x-ui.button></form>@endif
                                </div>
                            @endforeach
                            </div>
                        </section>
                    @endif
                </div>
                <section class="mx-4 mb-4 overflow-hidden rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm sm:mx-6 sm:mb-6">
                    <div class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4"><h3 class="font-bold text-[var(--ui-fg-strong)]">Antrean transaksi tertunda</h3><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">Daftar ini hanya menampilkan transaksi yang masih memerlukan penyelesaian sebelum penomoran atau penutupan periode.</p></div>
                    <livewire:spj-monitoring-list />
                </section>
