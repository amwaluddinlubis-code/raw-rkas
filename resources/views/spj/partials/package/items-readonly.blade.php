                            <div class="flex items-center justify-between border-b border-[var(--ui-line)] px-4 py-3">
                                <div><h2 class="text-base font-bold text-[var(--ui-fg-strong)]">Rincian Paket</h2><p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">{{ $transaction->items->count() }} item yang akan dipakai dokumen SPJ.</p></div>
                                <span class="hidden sm:inline-flex rounded-full bg-[var(--ui-surface-muted)] px-2.5 py-1 text-xs font-bold text-[var(--ui-fg)]">{{ $rupiah($transaction->items->sum('amount')) }}</span>
                            </div>
                            <div class="divide-y divide-[var(--ui-line)]">
                                @forelse($transaction->items as $index => $item)
                                    @php($spjDescription = $item->item_description ?: ($transaction->is_siplah ? ($item->siplah_item_name ?: $item->sourceValue('description')) : $item->sourceValue('description')))
                                    <div class="flex gap-3 px-4 py-3">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[var(--ui-surface-muted)] text-[11px] font-bold text-[var(--ui-fg-muted)]">{{ $index + 1 }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-base font-medium leading-tight text-[var(--ui-fg-strong)]">{{ $spjDescription }}</p>
                                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--ui-fg-muted)]">
                                                <span><span class="font-semibold text-[var(--ui-fg)]">Volume:</span> {{ $item->sourceValue('quantity') }}</span>
                                                <span><span class="font-semibold text-[var(--ui-fg)]">Satuan:</span> {{ $item->sourceValue('unit') ?: '—' }}</span>
                                                <span><span class="font-semibold text-[var(--ui-fg)]">Harga Satuan:</span> {{ $rupiah($item->sourceValue('unit_price')) }}</span>
                                                <span><span class="font-semibold text-[var(--ui-fg)]">Rekening:</span> {{ $item->sourceValue('account_code') ?: $transaction->sourceValue('account_code') ?: '—' }}</span>
                                            </div>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Nilai</p>
                                            <p class="mt-0.5 text-base font-semibold text-[var(--ui-fg-strong)]">{{ $rupiah($item->sourceValue('amount')) }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="px-4 py-8 text-center text-base text-[var(--ui-fg-muted)]">Tidak ada rincian barang/jasa.</p>
                                @endforelse
                            </div>
