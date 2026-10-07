@php
    $listedPackages = $attributeList ?? collect();
@endphp
<div>
    <div class="border-b border-[var(--ui-line)] px-5 py-4 sm:px-6">
        <h2 class="font-bold" style="color: var(--ui-fg)">Attribut SPJ</h2>
        <p class="mt-1 text-sm" style="color: var(--ui-fg-muted)">Pemeriksaan atribut Data Umum Dokumen dan Data Pengadaan per kategori SPJ.</p>
    </div>
    @include('spj.partials.list-filter-bar', ['idPrefix' => 'spj-attribute'])
    <div class="grid gap-3 p-4 lg:hidden">
        @forelse($listedPackages as $listedPackage)
            @php
                $category = strtoupper((string) $listedPackage->transaction->spj_category);
                $transaction = $listedPackage->transaction;
            @endphp
            <a wire:key="spj-attribute-card-{{ $listedPackage->id }}" href="{{ route('spj.index', ['tab' => 'paket', 'package_id' => $listedPackage->id]) }}" class="rounded-xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" style="border-color: var(--ui-line); background: var(--ui-surface-base); color: var(--ui-fg)">
                <div class="flex items-start justify-between gap-3"><div><p class="font-mono text-sm font-bold theme-text">{{ $transaction->sourceValue('no_bukti') }}</p><p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $transaction->sourceCarbon()?->translatedFormat('d F Y') }}</p></div>@if($listedPackage->status === 'CANCELLED')<x-ui.status-badge status="CANCELLED" label="Dibatalkan" size="xs" />@elseif($listedPackage->document_number)<x-ui.status-badge status="NUMBERED" label="Bernomor" size="xs" />@else<x-ui.status-badge status="DRAFT" label="Draft" size="xs" />@endif</div>
                <p class="mt-2 line-clamp-2 text-sm font-semibold" style="color: var(--ui-fg)">{{ $transaction->payment_description ?: $transaction->sourceValue('description') }}</p>
                <div class="mt-3 flex items-center justify-between text-xs" style="color: var(--ui-fg-muted)"><span style="color: var(--theme-content-accent)">{{ \App\Support\SpjDisplay::typeLabel($category) }}</span><b style="color: var(--ui-fg)">{{ \App\Support\SpjDisplay::rupiah($transaction->sourceValue('gross_amount')) }}</b></div>
                @include('spj.partials.attribute-card-detail', ['transaction' => $transaction, 'package' => $listedPackage])
            </a>
        @empty
            <div class="rounded-xl border border-dashed p-8 text-center" style="border-color: var(--ui-line); color: var(--ui-fg-muted)">Belum ada paket SPJ. Siapkan transaksi dari tab Persiapan.</div>
        @endforelse
    </div>
    <div class="hidden overflow-x-auto lg:block">
        <x-ui.table pagination="server">
            <thead class="bg-[var(--ui-surface-soft)]">
                <tr>
                    <th class="px-5 py-2 text-left text-xs font-bold uppercase text-slate-500">Bukti / Tanggal</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase text-slate-500">Kategori</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase text-slate-500">Data Umum Dokumen</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase text-slate-500">Data Pengadaan / Kategori</th>
                    <th class="px-4 py-2 text-left text-xs font-bold uppercase text-slate-500">Status</th>
                    <th class="px-5 py-2 text-right text-xs font-bold uppercase text-slate-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ui-line)]">
                @forelse($listedPackages as $listedPackage)
                    @php
                        $category = strtoupper((string) $listedPackage->transaction->spj_category);
                        $transaction = $listedPackage->transaction;
                    @endphp
                    <tr wire:key="spj-attribute-{{ $listedPackage->id }}" class="hover:bg-slate-50">
                        <td class="px-5 py-2.5">
                            <p class="font-mono font-bold theme-text">{{ $transaction->sourceValue('no_bukti') }}</p>
                            <p class="mt-1 text-xs" style="color: var(--ui-fg-muted)">{{ $transaction->sourceCarbon()?->translatedFormat('d F Y') }}</p>
                        </td>
                        <td class="px-4 py-2.5 text-xs font-bold" style="color: var(--theme-content-accent)">{{ \App\Support\SpjDisplay::typeLabel($category) }}</td>
                        <td class="px-4 py-2.5 max-w-md">
                            @include('spj.partials.attribute-data-umum', ['transaction' => $transaction, 'package' => $listedPackage])
                        </td>
                        <td class="px-4 py-2.5 max-w-md">
                            @include('spj.partials.attribute-data-pengadaan', ['transaction' => $transaction, 'package' => $listedPackage, 'category' => $category])
                        </td>
                        <td class="px-4 py-2.5">
                            @if($listedPackage->status === 'CANCELLED')
                                <x-ui.status-badge status="CANCELLED" label="Dibatalkan" />
                            @elseif($listedPackage->document_number)
                                <x-ui.status-badge status="NUMBERED" label="Bernomor" />
                            @else
                                <x-ui.status-badge status="DRAFT" label="Draft" />
                            @endif
                            @if($listedPackage->document_number)
                                <p class="mt-1 font-mono text-[11px]" style="color: var(--ui-fg-muted)">{{ $listedPackage->document_number }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 text-right">
                            <x-ui.button :href="route('spj.index', ['tab' => 'paket', 'package_id' => $listedPackage->id])">Buka paket →</x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-center" style="color: var(--ui-fg-muted)">Belum ada paket SPJ. Siapkan transaksi dari tab Persiapan.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </div>
    @if($attributeList->hasPages())
        <x-ui.server-pagination :paginator="$attributeList" noun="paket" />
    @endif
</div>
