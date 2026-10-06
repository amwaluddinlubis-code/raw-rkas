                            <div class="flex items-center justify-between border-b border-[var(--ui-line)] px-4 py-3">
                                <div>
                                    <h2 class="text-base font-bold text-[var(--ui-fg-strong)]">Rincian Paket</h2>
                                    <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">
                                        {{ $transaction->items->count() }} item yang akan dipakai dokumen SPJ.</p>
                                </div>
                                <span
                                    class="hidden sm:inline-flex rounded-full bg-[var(--ui-surface-muted)] px-2.5 py-1 text-xs font-bold text-[var(--ui-fg)]">{{ $rupiah($transaction->items->sum('amount')) }}</span>
                            </div>
                            <div class="divide-y divide-[var(--ui-line)]">
                                @forelse($transaction->items as $index => $item)
                                    @php($spjDescription = $item->item_description ?: ($transaction->is_siplah ? ($item->siplah_item_name ?: $item->sourceValue('description')) : $item->sourceValue('description')))
                                    <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 px-4 py-2">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <span
                                                class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[var(--ui-surface-muted)] text-[11px] font-bold text-[var(--ui-fg-muted)]">{{ $index + 1 }}</span>
                                            <p class="truncate text-sm font-medium leading-tight text-[var(--ui-fg-strong)]"
                                                title="{{ $spjDescription }}">{{ $spjDescription }}</p>
                                        </div>
                                        <div
                                            class="shrink-0 space-y-0.5 border-l border-[var(--ui-line)] pl-3 text-xs text-[var(--ui-fg-muted)]">
                                            <p class="truncate">
                                                <span class="font-semibold text-[var(--ui-fg)]">Volume</span>
                                                {{ $item->sourceValue('quantity') }}
                                                <span class="font-semibold text-[var(--ui-fg)]">Satuan</span>
                                                {{ $item->sourceValue('unit') ?: '—' }}
                                                <span class="font-semibold text-[var(--ui-fg)]">Harga</span>
                                                {{ $rupiah($item->sourceValue('unit_price')) }}
                                                <span class="font-semibold text-[var(--ui-fg)]">Rek</span>
                                                {{ $item->sourceValue('account_code') ?: $transaction->sourceValue('account_code') ?: '—' }}
                                            </p>
                                        </div>
                                        <p
                                            class="shrink-0 border-l border-[var(--ui-line)] pl-3 text-right text-sm font-semibold text-[var(--ui-fg-strong)]">
                                            {{ $rupiah($item->sourceValue('amount')) }}</p>
                                    </div>
                                @empty
                                    <p class="px-4 py-8 text-center text-base text-[var(--ui-fg-muted)]">Tidak ada
                                        rincian barang/jasa.</p>
                                @endforelse
                            </div>
