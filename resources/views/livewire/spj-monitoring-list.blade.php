<div>
    <div class="overflow-x-auto p-5"><x-ui.table pagination="server"><thead><tr><th class="px-4 py-2 text-left text-xs font-bold">BUKTI</th><th class="px-4 py-2 text-left text-xs font-bold">URAIAN</th><th class="px-4 py-2 text-left text-xs font-bold">STATUS</th><th class="px-4 py-2 text-right text-xs font-bold">AKSI</th></tr></thead><tbody>@forelse($pendingPaginator ?? [] as $transaction)@php
            $wasCancelled = $transaction->spjPackage?->documents?->contains('status', 'CANCELLED') ?? false;
        @endphp<tr wire:key="spj-monitoring-{{ $transaction->id }}" class="spj-monitoring-row transition {{ $wasCancelled ? 'is-cancelled' : '' }}"><td class="px-4 py-2 font-mono font-bold">{{ $transaction->sourceValue('no_bukti') }}</td><td class="px-4 py-2 max-w-sm truncate">{{ $transaction->sourceValue('description') }}</td><td class="px-4 py-2">@if ($wasCancelled)
        @php($monitorStatus = 'CANCELLED')
        @php($monitorLabel = 'Dibatalkan — menunggu nomor baru')
    @elseif ($transaction->spjPackage)
        @php($monitorStatus = 'DRAFT')
        @php($monitorLabel = 'Draft — nomor belum ditetapkan')
    @else
        @php($monitorStatus = 'BELUM_LENGKAP')
        @php($monitorLabel = 'Paket belum disiapkan')
    @endif
    <x-ui.status-badge :status="$monitorStatus" :label="$monitorLabel" size="xs" /></td><td class="px-4 py-2 text-right">@if($transaction->spjPackage)<a href="{{ $wasCancelled ? route('spj.index', ['tab' => 'paket', 'package_id' => $transaction->spjPackage->id]) : route('spj.checklist', $transaction->spjPackage->id) }}" class="ui-btn ui-btn-secondary !min-h-8 !px-3 !py-1.5 text-xs">{{ $wasCancelled ? 'Periksa paket →' : 'Lihat checklist →' }}</a>@else<a href="{{ route('spj.index', ['tab' => 'persiapan', 'state' => 'unprepared']) }}" class="ui-btn ui-btn-secondary !min-h-8 !px-3 !py-1.5 text-xs">Buka persiapan →</a>@endif</td></tr>@empty<tr><td colspan="4" class="px-5 py-10 text-center text-[var(--ui-fg-muted)]">Tidak ada transaksi ber-rincian yang tertunda.</td></tr>@endforelse</tbody></x-ui.table>    </div>
    @if(($pendingPaginator?->hasPages() ?? false))
        <x-ui.server-pagination :paginator="$pendingPaginator" noun="transaksi" />
    @endif
</div>
