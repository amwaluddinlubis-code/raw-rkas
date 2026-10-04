<x-layouts.tailwind-app>
    @php
        $rupiah = fn($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $counts = $recap['counts'];
        $totals = $recap['totals'];
    @endphp
    <div class="spj-semantic-workspace space-y-6">
        <x-page-header
            :title="'Rekap Triwulan '.$recap['quarter']"
            subtitle="Satu halaman untuk Bendahara/Kepsek — status paket dan total nilai sumber dalam konteks aktif."
            kicker="Rekap SPJ per Triwulan"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'persiapan'])" class="print:hidden">Kembali ke antrean</x-ui.button>
                <x-ui.button variant="secondary" type="button" onclick="window.print()" class="print:hidden">Cetak rekap</x-ui.button>
            </x-slot:actions>

            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                <x-stat-item label="Transaksi triwulan" :value="$counts['transactions']" :hint="'Tanpa paket: '.$counts['without_package']" value-class="text-[var(--theme-content-accent)]" icon="archive" icon-class="text-[var(--theme-content-accent)]" />
                <x-stat-item label="Perlu dilengkapi (DRAFT)" :value="$counts['draft']" :hint="'Siap dinomori (READY): '.$counts['ready']" value-class="text-amber-700" icon="warning" icon-class="text-amber-700" />
                <x-stat-item label="Bernomor + Final" :value="$counts['numbered'] + $counts['final']" :hint="'Dibatalkan: '.$counts['cancelled']" value-class="text-emerald-700" icon="document" icon-class="text-emerald-700" />
                <x-stat-item label="Total bruto sumber" :value="$rupiah($totals['gross'])" :hint="'Pajak '.$rupiah($totals['tax']).' · Netto '.$rupiah($totals['net'])" value-class="text-slate-800" icon="work" icon-class="text-slate-800" />
            </div>
        </x-page-header>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="flex flex-col gap-3 border-b border-[var(--ui-line)] px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Pilih triwulan</h2>
                </div>
                <form method="GET" action="{{ route('spj.quarter-recap') }}" class="flex items-center gap-2 print:hidden">
                    <x-ui.field label="Triwulan">
                        <x-ui.select name="quarter" onchange="this.form.submit()">
                            @foreach (range(1, 4) as $q)
                                <option value="{{ $q }}" {{ $q === $recap['quarter'] ? 'selected' : '' }}>Triwulan {{ $q }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </form>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-3">
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Siap dinomori — READY ({{ $recap['ready']->count() }})</h2>
                <p class="mt-0.5 text-xs leading-5 text-[var(--ui-fg-muted)]">Maksimal 50 baris pertama. Buka checklist paket untuk memproses penomoran.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                    <thead class="bg-[var(--ui-surface-soft)]">
                        <tr>
                            <th class="px-5 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Bukti / Tanggal</th>
                            <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Uraian</th>
                            <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Nilai</th>
                            <th class="px-5 py-2 text-center text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)] print:hidden">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                        @forelse($recap['ready'] as $transaction)
                            <tr class="transition hover:bg-[var(--ui-table-row-hover)]">
                                <td class="px-5 py-2">
                                    <p class="font-mono font-bold text-[var(--theme-content-accent)]">{{ $transaction->sourceValue('no_bukti') }}</p>
                                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->sourceCarbon()?->translatedFormat('d F Y') }}</p>
                                </td>
                                <td class="max-w-sm px-4 py-2">
                                    <p class="truncate font-semibold text-[var(--ui-fg-strong)]">{{ $transaction->payment_description ?: $transaction->sourceValue('description') ?: 'Tanpa uraian' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-[var(--ui-fg-strong)]">{{ $rupiah($transaction->sourceValue('gross_amount')) }}</td>
                                <td class="px-5 py-2 text-center print:hidden"><x-ui.button variant="secondary" :href="route('spj.checklist', $transaction->spjPackage->id)" class="text-xs">Checklist →</x-ui.button></td>
                            </tr>
                        @empty<tr><td colspan="4" class="px-5 py-10 text-center text-sm text-[var(--ui-fg-muted)]">Tidak ada paket READY pada triwulan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
            <div class="border-b border-[var(--ui-line)] px-5 py-3">
                <h2 class="font-bold text-[var(--ui-fg-strong)]">Perlu dilengkapi — DRAFT ({{ $recap['draft']->count() }})</h2>
                <p class="mt-0.5 text-xs leading-5 text-[var(--ui-fg-muted)]">Maksimal 50 baris pertama.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--ui-line)] text-sm">
                    <thead class="bg-[var(--ui-surface-soft)]">
                        <tr>
                            <th class="px-5 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Bukti / Tanggal</th>
                            <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Uraian</th>
                            <th class="px-4 py-2 text-right text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Nilai</th>
                            <th class="px-5 py-2 text-center text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)] print:hidden">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                        @forelse($recap['draft'] as $transaction)
                            <tr class="transition hover:bg-[var(--ui-table-row-hover)]">
                                <td class="px-5 py-2">
                                    <p class="font-mono font-bold text-[var(--theme-content-accent)]">{{ $transaction->sourceValue('no_bukti') }}</p>
                                    <p class="mt-1 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->sourceCarbon()?->translatedFormat('d F Y') }}</p>
                                </td>
                                <td class="max-w-sm px-4 py-2">
                                    <p class="truncate font-semibold text-[var(--ui-fg-strong)]">{{ $transaction->payment_description ?: $transaction->sourceValue('description') ?: 'Tanpa uraian' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-[var(--ui-fg-strong)]">{{ $rupiah($transaction->sourceValue('gross_amount')) }}</td>
                                <td class="px-5 py-2 text-center print:hidden"><x-ui.button variant="secondary" :href="route('spj.checklist', $transaction->spjPackage->id)" class="text-xs">Checklist →</x-ui.button></td>
                            </tr>
                        @empty<tr><td colspan="4" class="px-5 py-10 text-center text-sm text-[var(--ui-fg-muted)]">Tidak ada paket DRAFT pada triwulan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.tailwind-app>
