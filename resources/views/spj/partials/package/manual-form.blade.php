{{-- Form Isian Manual paket SPJ: persiapan data + form update paket (kategori, Data Umum, fieldset per kategori, aksi simpan). --}}
                            @php
                                $workDetails = $transaction->workOrder;
                                $workerRows = $transaction->workers->map(fn ($worker) => [
                                    'name' => $worker->name,
                                    'job_description' => $worker->job_description,
                                    'work_days' => $worker->work_days,
                                    'daily_rate' => $worker->daily_rate,
                                    'is_receipt_recipient' => (bool) $worker->is_receipt_recipient,
                                    'notes' => $worker->notes,
                                ])->values()->all();
                                $workerRows = old('spj_category', $transaction->spj_category) === 'PEMELIHARAAN' ? old('workers', $workerRows) : $workerRows;
                                $selectedSpjType = strtoupper((string) old('spj_category', $transaction->spj_category ?: $transaction->spj_category));
                            @endphp
                            <form id="spj-manual-form" method="POST" action="{{ route('spj.update', $package->id) }}" data-source-siplah="{{ $transaction->is_siplah ? '1' : '0' }}" class="space-y-4 p-4" @submit="if (!$event.defaultPrevented) saving=true">@csrf @method('PUT')
                    @unless($package->isEditable())<div class="flex items-start gap-2 rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-muted)] px-3 py-2 text-sm text-[var(--ui-fg)]"><span aria-hidden="true">🔒</span><p><strong>Isian terkunci.</strong> Batalkan nomor dan buka paket untuk koreksi agar field dapat diedit kembali.</p></div>@endunless
                    <fieldset @disabled(!$package->isEditable()) class="disabled:cursor-not-allowed disabled:opacity-60">
                    <div x-show="saving" class="flex items-center justify-center py-4"><x-loading-spinner /></div>
                    <div x-show="!saving">
                                @include('spj.partials.package.category-card')
                                @include('spj.partials.package.common')
                                @include('spj.partials.package.categories.honor-pegawai')
                                @include('spj.partials.package.categories.sppd')
                                @include('spj.partials.package.categories.barang')
@include('spj.partials.package.categories.konsumsi')
@include('spj.partials.package.categories.pemeliharaan')
                                @include('spj.partials.package.categories.jasa-lainnya')
                                <x-ui.sticky-actions class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                    @if($previousPackageId ?? null)
                                        <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'paket', 'package_id' => $previousPackageId, 'package_tab' => 'isian'])" title="Buka paket sebelumnya pada tahun anggaran dan sumber dana aktif" class="justify-center">← Prev</x-ui.button>
                                    @else
                                        <x-ui.button variant="secondary" disabled title="Tidak ada paket sebelumnya pada tahun anggaran dan sumber dana aktif" class="justify-center opacity-55">← Prev</x-ui.button>
                                    @endif
                                    <x-ui.button type="submit" x-bind:disabled="saving" class="justify-center px-4 py-1.5 text-base"><span x-show="saving" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span> <span x-text="saving ? 'Menyimpan...' : 'Simpan Isian Paket'"></span></x-ui.button>
                                    @if($nextPackageId ?? null)
                                        <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'paket', 'package_id' => $nextPackageId, 'package_tab' => 'isian'])" title="Buka paket berikutnya pada tahun anggaran dan sumber dana aktif" class="justify-center">Next →</x-ui.button>
                                    @else
                                        <x-ui.button variant="secondary" disabled title="Tidak ada paket berikutnya pada tahun anggaran dan sumber dana aktif" class="justify-center opacity-55">Next →</x-ui.button>
                                    @endif
                                </x-ui.sticky-actions>
                    </div>
                    </fieldset>
                            </form>
