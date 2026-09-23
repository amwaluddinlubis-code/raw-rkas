@php
    $primaryNameKey = 'name';
    $receiptRecipientName = trim((string) ($transaction->receipt_recipient_name ?? ''));
    $initialPrimaryIndex = collect($rows)->search(function ($row) use ($receiptRecipientName): bool {
        return $receiptRecipientName !== ''
            && is_array($row)
            && mb_strtolower(trim((string) ($row['name'] ?? ''))) === mb_strtolower($receiptRecipientName);
    });
    if ($initialPrimaryIndex === false) {
        $initialPrimaryIndex = count($rows) > 0 ? 0 : null;
    }
@endphp

<div
    x-data="{
        keySequence: 0,
        rows: @js(array_values($rows)).map((row, index) => ({...row, _key: `saved-${index}`})),
        emptyRow: @js($emptyRow),
        primaryIndex: @js($initialPrimaryIndex),
        orderSources: @js($orderSources ?? []),
        copySourceId: '',
        savedOrder: Alpine.$persist([], 'spj-jasa-order-{{ (int) session('active_school_id') }}-{{ (int) $transaction->fiscal_year_id }}'),
        nextKey(prefix = 'service') { this.keySequence += 1; return `${prefix}-${Date.now()}-${this.keySequence}`; },
        addRow() {
            this.rows.push({...this.emptyRow, _key: this.nextKey()});
            if (this.primaryIndex === null) this.primaryIndex = 0;
        },
        removeRow(index) {
            this.rows.splice(index, 1);
            if (this.rows.length === 0) {
                this.primaryIndex = null;
            } else if (this.primaryIndex === index) {
                this.primaryIndex = Math.min(index, this.rows.length - 1);
            } else if (this.primaryIndex > index) {
                this.primaryIndex -= 1;
            }
        },
        moveRow(fromIndex, toIndex) {
            if (fromIndex < 0 || toIndex < 0 || fromIndex >= this.rows.length || toIndex >= this.rows.length || fromIndex === toIndex) return;
            const primaryRow = this.primaryIndex !== null ? this.rows[this.primaryIndex] : null;
            const [moved] = this.rows.splice(fromIndex, 1);
            this.rows.splice(toIndex, 0, moved);
            if (primaryRow) this.primaryIndex = this.rows.indexOf(primaryRow);
        },
        startDrag(index, event) {
            this.draggedKey = this.rows[index]?._key || null;
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', this.draggedKey || String(index));
            }
        },
        dropAt(index) {
            if (!this.draggedKey) return;
            const fromIndex = this.rows.findIndex(row => row._key === this.draggedKey);
            this.moveRow(fromIndex, index);
            this.draggedKey = null;
        },
        sortByName() {
            const primaryRow = this.primaryIndex !== null ? this.rows[this.primaryIndex] : null;
            this.rows.sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? ''), 'id'));
            if (primaryRow) this.primaryIndex = this.rows.indexOf(primaryRow);
        },
        applyNameOrder(names) {
            const rank = new Map();
            names.forEach((name, i) => { const key = String(name ?? '').trim().toLowerCase(); if (key && !rank.has(key)) rank.set(key, i); });
            const primaryRow = this.primaryIndex !== null ? this.rows[this.primaryIndex] : null;
            this.rows = this.rows.map((row, i) => ({row, i})).sort((a, b) => {
                const ra = rank.get(String(a.row.name ?? '').trim().toLowerCase());
                const rb = rank.get(String(b.row.name ?? '').trim().toLowerCase());
                if (ra === undefined && rb === undefined) return a.i - b.i;
                if (ra === undefined) return 1;
                if (rb === undefined) return -1;
                return ra - rb;
            }).map(({row}) => row);
            if (primaryRow) this.primaryIndex = this.rows.indexOf(primaryRow);
        },
        saveOrder() { this.savedOrder = this.rows.map(row => row.name).filter(name => String(name ?? '').trim() !== ''); },
        applySavedOrder() { if (this.savedOrder.length) this.applyNameOrder(this.savedOrder); },
        copyFromPackage() {
            const source = this.orderSources.find(item => String(item.id) === String(this.copySourceId));
            if (source) this.applyNameOrder(source.names);
        },
        copyRecipients() {
            const source = this.orderSources.find(item => String(item.id) === String(this.copySourceId));
            if (!source?.recipients?.length) return;
            this.rows = source.recipients.map(row => ({...row, _key: this.nextKey('copied')}));
            this.primaryIndex = this.rows.length ? 0 : null;
        },
        parseAccounting(value) { const digits = String(value ?? '').replace(/[^0-9]/g, ''); return digits === '' ? 0 : Number(digits); },
        accounting(value) { return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0); }
    }"
    class="mt-3"
>
    <input type="hidden" name="primary_recipient_group" value="service_recipients">
    <input type="hidden" name="primary_recipient_index" :value="primaryIndex ?? ''">

    <div class="mb-3 flex items-center justify-between gap-3">
        <p class="text-xs" style="color: var(--ui-fg-muted)">Isi penerima jasa dan nilai sampai totalnya sesuai dengan bruto transaksi.</p>
        <div class="flex flex-wrap gap-2">
            <select x-show="orderSources.length > 0" x-model="copySourceId" class="h-8 min-w-48 max-w-full rounded border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-2 py-0 text-xs" aria-label="Salin dari paket jasa lainnya">
                <option value="">Salin dari paket jasa...</option>
                <template x-for="source in orderSources" :key="source.id"><option :value="source.id" x-text="source.label"></option></template>
            </select>
            <button type="button" x-show="orderSources.length > 0" @click="copyFromPackage()" :disabled="!copySourceId" class="ui-btn ui-btn-secondary !min-h-8 !px-2.5 !py-1 text-xs">Salin urutan</button>
            <button type="button" x-show="orderSources.length > 0" @click="copyRecipients()" :disabled="!copySourceId" class="ui-btn ui-btn-secondary !min-h-8 !px-2.5 !py-1 text-xs">Salin penerima</button>
        </div>
        <x-ui.button type="button" variant="secondary" x-on:click="addRow()" class="shrink-0 !min-h-8 !px-2.5 !py-1 text-xs">
            <span aria-hidden="true">＋</span> Tambah Penerima
        </x-ui.button>
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Urutkan penerima:</span>
        <button type="button" @click="sortByName()" class="ui-btn ui-btn-secondary !min-h-8 !px-2.5 !py-1 text-xs">A–Z</button>
        <button type="button" @click="saveOrder()" class="ui-btn ui-btn-secondary !min-h-8 !px-2.5 !py-1 text-xs">Simpan urutan</button>
        <button type="button" x-show="savedOrder.length > 0" @click="applySavedOrder()" class="ui-btn ui-btn-secondary !min-h-8 !px-2.5 !py-1 text-xs">Pakai tersimpan</button>
    </div>

    <div class="space-y-3">
        <template x-for="(row, index) in rows" :key="row._key">
            <article draggable="true" @dragstart.stop="startDrag(index, $event)" @dragover.prevent @drop.prevent="dropAt(index)" class="rounded-lg border p-3" style="border-color: var(--ui-line); background: var(--ui-surface-base)">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="cursor-grab text-[var(--ui-fg-muted)]" title="Seret untuk mengurutkan">⋮⋮</span>
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold" style="background: var(--theme-accent-soft); color: var(--theme-content-accent)" x-text="index + 1"></span>
                        <span class="text-xs font-bold uppercase tracking-wide" style="color: var(--ui-fg-muted)">Penerima jasa</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="inline-flex items-center gap-1.5 text-xs" style="color: var(--ui-fg-muted)">
                            <input type="radio" :checked="primaryIndex === index" @change="primaryIndex = index" class="h-4 w-4" title="Jadikan penerima utama">
                            Utama
                        </label>
                        <button type="button" x-on:click="removeRow(index)" title="Hapus penerima" class="inline-flex h-7 w-7 items-center justify-center rounded border text-sm font-bold text-rose-700 transition hover:bg-rose-50" style="border-color: var(--ui-line)">×</button>
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
                    <label class="sm:col-span-2 lg:col-span-2">
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Nama Penerima <span class="text-rose-600">*</span></span>
                        <input type="text" :name="'service_recipients[' + index + '][name]'" x-model="row.name" required class="ui-input mt-1 !min-h-8 !py-1.5 text-xs">
                    </label>
                    <label>
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Jenis Jasa</span>
                        <input type="text" :name="'service_recipients[' + index + '][service_type]'" x-model="row.service_type" class="ui-input mt-1 !min-h-8 !py-1.5 text-xs">
                    </label>
                    <label>
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Volume</span>
                        <input type="number" min="0" step="1" inputmode="numeric" :name="'service_recipients[' + index + '][quantity]'" x-model.number="row.quantity" class="ui-input mt-1 !min-h-8 !py-1.5 text-right font-mono text-xs">
                    </label>
                    <label>
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Hari / Kali</span>
                        <input type="number" min="0" step="1" inputmode="numeric" :name="'service_recipients[' + index + '][rental_days]'" x-model.number="row.rental_days" class="ui-input mt-1 !min-h-8 !py-1.5 text-right font-mono text-xs">
                    </label>
                    <label>
                        <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Tarif (Rp)</span>
                        <input type="hidden" :name="'service_recipients[' + index + '][daily_rate]'" :value="Number(row.daily_rate) || 0">
                        <input type="text" inputmode="numeric" :value="accounting(row.daily_rate)" @input="row.daily_rate = parseAccounting($event.target.value); $event.target.value = accounting(row.daily_rate)" class="ui-input mt-1 !min-h-8 !py-1.5 text-right font-mono text-xs" aria-label="Tarif jasa dalam rupiah">
                    </label>
                </div>

                <details class="mt-3 rounded-md border px-3 py-2" style="border-color: var(--ui-line); background: var(--ui-surface-soft)">
                    <summary class="cursor-pointer text-xs font-semibold" style="color: var(--ui-fg-muted)">Detail tambahan</summary>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach([
                            'npwp' => ['NPWP', 'text'],
                            'unit' => ['Satuan', 'text'],
                            'usage_started_at' => ['Mulai', 'date'],
                            'usage_completed_at' => ['Selesai', 'date'],
                            'receipt_number' => ['Ref. Kuitansi', 'text'],
                            'payment_reference' => ['Ref. Pembayaran', 'text'],
                            'agreement_number' => ['No. Perjanjian', 'text'],
                            'agreement_date' => ['Tgl Perjanjian', 'date'],
                        ] as $key => [$label, $type])
                            <label>
                                <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">{{ $label }}</span>
                                <input
                                    type="{{ $type }}"
                                    :name="'service_recipients[' + index + '][{{ $key }}]'"
                                    x-model="row.{{ $key }}"
                                    @if ($key === 'usage_started_at') :max="row.usage_completed_at || null" @endif
                                    @if ($key === 'usage_completed_at') :min="row.usage_started_at || null" @endif
                                    class="ui-input mt-1 !min-h-8 !py-1.5 text-xs"
                                >
                            </label>
                        @endforeach
                        <label class="sm:col-span-2 lg:col-span-4">
                            <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Uraian Jasa</span>
                            <textarea rows="2" :name="'service_recipients[' + index + '][service_description]'" x-model="row.service_description" class="ui-textarea mt-1 !min-h-16 !py-1.5 text-xs"></textarea>
                        </label>
                        <label class="sm:col-span-2 lg:col-span-4">
                            <span class="text-xs font-semibold" style="color: var(--ui-fg-muted)">Catatan</span>
                            <textarea rows="2" :name="'service_recipients[' + index + '][notes]'" x-model="row.notes" class="ui-textarea mt-1 !min-h-16 !py-1.5 text-xs"></textarea>
                        </label>
                    </div>
                </details>
            </article>
        </template>

        <div x-show="rows.length === 0" class="rounded-lg border border-dashed px-3 py-6 text-center text-xs" style="border-color: var(--ui-line); color: var(--ui-fg-muted)">
            Belum ada penerima jasa. Klik “Tambah Penerima”.
        </div>
    </div>
</div>
