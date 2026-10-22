{{-- Kartu kategori SPJ pada form Isian Manual: select kategori + konteks sebaris (radio SiPLah / slot Bahan-Upah). --}}
                                <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                                    <div class="grid gap-3 lg:grid-cols-4 lg:items-end" data-spj-category-grid>
                                        <div class="min-w-0 lg:col-span-1" data-spj-category-field>
                                            <label for="spj-type" class="text-xs font-bold text-amber-900">Kategori SPJ <span class="text-rose-600">*</span></label>
                                            <x-ui.select id="spj-type" name="spj_category" class="mt-1">
                                                <option value="">Pilih kategori</option>
                                                @foreach(['BARANG','KONSUMSI','PEMELIHARAAN','JASA_LAINNYA','SPPD','HONOR_PEGAWAI'] as $value)
                                                    <option value="{{ $value }}" @selected(in_array($selectedSpjType, ['JASA_HONORARIUM', 'HONOR_PEGAWAI']) && in_array(strtoupper((string) $value), ['JASA_HONORARIUM', 'HONOR_PEGAWAI']) || old('spj_category', $transaction->spj_category ?: $transaction->spj_category) === $value)>{{ \App\Support\SpjDisplay::typeLabel($value) }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        </div>
                                        <div data-spj-category-context class="min-w-0 lg:col-span-3">
                                            <div data-spj-category-context-panel="BARANG" @unless($selectedSpjType === 'BARANG') hidden @endunless>
                                                <div class="flex min-h-10 flex-wrap items-center gap-x-7 gap-y-2 rounded-md border border-amber-300 bg-[var(--ui-surface-base)] px-3 py-1.5 text-sm">
                                                    <label class="inline-flex cursor-pointer items-center gap-2 font-semibold text-[var(--ui-fg-strong)]">
                                                        <input type="radio" name="spj_siplah_mode_ui" data-spj-siplah-mode="siplah" value="siplah" class="h-4 w-4 border-amber-300 text-indigo-600 focus:ring-indigo-500" @checked($transaction->is_siplah || $transaction->payment_method === 'siplah')>
                                                        <span>SiPLah</span>
                                                    </label>
                                                    <label class="inline-flex cursor-pointer items-center gap-2 font-semibold text-[var(--ui-fg-strong)]">
                                                        <input type="radio" name="spj_siplah_mode_ui" data-spj-siplah-mode="non_siplah" value="non_siplah" class="h-4 w-4 border-amber-300 text-indigo-600 focus:ring-indigo-500" @checked(!($transaction->is_siplah || $transaction->payment_method === 'siplah')) @disabled($transaction->is_siplah)>
                                                        <span>Non SiPLah</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div data-spj-category-context-panel="PEMELIHARAAN" @unless($selectedSpjType === 'PEMELIHARAAN') hidden @endunless>
                                                <div data-spj-maintenance-quick-slot class="min-w-0"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-amber-800">Kategori menentukan field manual, dokumen pendukung, dan nomor yang diterbitkan. Subkategori terpisah tidak diperlukan.</p>
                                </div>
