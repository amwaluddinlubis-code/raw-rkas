<x-layouts.tailwind-app>
    <div class="space-y-6">
        <x-page-header title="Susun Laporan Pembayaran Honor"
            subtitle="Transaksi sumber tetap utuh; halaman ini menyusun rekap berdasarkan transaksi yang Anda pilih."
            kicker="LAPORAN SPJ · HONOR PEGAWAI">
            <x-slot:actions><x-ui.button variant="secondary" :href="route('spj.honor-payments.select')">Ubah Pilihan</x-ui.button></x-slot:actions>
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-3 sm:divide-x sm:divide-y-0"><x-stat-item label="Transaksi dipilih" :value="$transactions->count()" hint="Sumber rekap" value-class="text-slate-800" icon="report" icon-class="text-slate-800" /><x-stat-item label="Total bruto" :value="number_format($summary['gross'], 0, ',', '.')" hint="Sebelum potongan" value-class="text-indigo-700" icon="budget" icon-class="text-indigo-700" /><x-stat-item label="Total diterima" :value="number_format($summary['net'], 0, ',', '.')" hint="Setelah potongan" value-class="text-emerald-700" icon="balance" icon-class="text-emerald-700" /></div>
        </x-page-header>
        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm"><div class="border-b border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-5 py-4"><h2 class="font-bold">Laporan Penerimaan Pembayaran Honor</h2><p class="mt-1 text-xs text-[var(--ui-fg-muted)]">No. Bukti/BPU dan Nomor SPJ ditampilkan pada kolom terpisah. Kolom Tanda Tangan berada paling kanan.</p></div><div class="overflow-x-auto p-5"><table class="min-w-full divide-y divide-[var(--ui-line)] text-sm"><thead class="bg-[var(--ui-surface-soft)]"><tr><th class="px-3 py-2">No</th><th class="px-3 py-2 text-left">No. Bukti/BPU</th><th class="px-3 py-2 text-left">Nomor SPJ</th><th class="px-3 py-2 text-left">Penerima</th><th class="px-3 py-2 text-left">Periode</th><th class="px-3 py-2 text-right">Bulan/Kali</th><th class="px-3 py-2 text-right">Bruto</th><th class="px-3 py-2 text-right">Potongan</th><th class="px-3 py-2 text-right">Diterima</th><th class="min-w-48 px-3 py-2 text-left">Tanda Tangan</th></tr></thead><tbody class="divide-y divide-[var(--ui-line)]">@forelse($rows as $index => $row)<tr><td class="px-3 py-2.5 text-center">{{ $index + 1 }}</td><td class="px-3 py-2.5 text-xs">{{ $row['proof_references'] ?: '-' }}</td><td class="px-3 py-2.5 text-xs">{{ $row['spj_references'] ?: '-' }}</td><td class="px-3 py-2.5 font-semibold">{{ $row['name'] }}<div class="text-xs font-normal text-[var(--ui-fg-muted)]">{{ $row['position'] }}</div></td><td class="px-3 py-2.5">{{ $row['period'] ?: '-' }}</td><td class="px-3 py-2.5 text-right">{{ $row['honor_units'] }}</td><td class="px-3 py-2.5 text-right">{{ number_format($row['gross'], 0, ',', '.') }}</td><td class="px-3 py-2.5 text-right">{{ number_format($row['tax'], 0, ',', '.') }}</td><td class="px-3 py-2.5 text-right font-bold">{{ number_format($row['net'], 0, ',', '.') }}</td><td class="px-3 py-2.5">{{ $index + 1 }}. __________________</td></tr>@empty<tr><td colspan="10" class="px-5 py-6 text-center">Tidak ada rincian honor pada transaksi yang dipilih.</td></tr>@endforelse</tbody></table></div></section>
        <div class="flex flex-wrap justify-end gap-3"><x-ui.button variant="secondary" :href="route('spj.honor-payments.select')">Kembali Pilih Transaksi</x-ui.button><button type="button" data-template-preview="{{ route('spj.honor-payments.export', ['format' => 'pdf', 'transaction_ids' => $transactions->modelKeys()]) }}" data-template-name="Pratinjau Laporan Pembayaran Honor" title="Pratinjau Laporan" class="ui-btn ui-btn-secondary"><x-ui.icon name="preview" size="sm" /><span>Pratinjau</span></button><a class="ui-button" href="{{ route('spj.honor-payments.export', ['format' => 'xlsx', 'transaction_ids' => $transactions->modelKeys()]) }}">Unduh Excel</a></div>
    </div>

    @include('spj.partials.preview-modal')

    <script>
        (() => {
            const templatePreview = (action, button) => {
                const modal = document.getElementById('template-preview-modal');
                if (!modal) return;
                const frame = document.getElementById('template-preview-frame');
                const title = document.getElementById('template-preview-title');
                if (action === 'close') {
                    modal.classList.add('hidden'); modal.classList.remove('flex');
                    if (frame) frame.src = 'about:blank';
                    return;
                }
                if (!button || !frame) return;
                if (title) title.textContent = button.dataset.templateName || 'Pratinjau Template';
                frame.src = button.dataset.templatePreviewPdf || button.dataset.templatePreview;
                modal.classList.remove('hidden'); modal.classList.add('flex');
            };
            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-close-template-preview]')) {
                    templatePreview('close');
                    return;
                }
                const modal = document.getElementById('template-preview-modal');
                if (modal && !modal.classList.contains('hidden') && event.target === modal) {
                    templatePreview('close');
                    return;
                }
                const button = event.target.closest('[data-template-preview]');
                if (!button || button.closest('[inert]')) return;
                templatePreview('open', button);
            });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') templatePreview('close'); });
        })();
    </script>
</x-layouts.tailwind-app>
