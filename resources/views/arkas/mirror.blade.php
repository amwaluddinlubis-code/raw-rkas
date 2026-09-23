<x-layouts.tailwind-app>
    <div class="space-y-6" x-data="sinkronisasiArkas()" x-init="init()">
        <x-page-header
            title="Pusat Sinkronisasi ARKAS"
            subtitle="Dua lajur tetap: 13 tabel referensi mengalir ke database pusat, 17 tabel operasional mengalir ke database sekolah aktif. Tanpa mapping manual, overlay SPJ selalu dipertahankan."
            kicker="Data & Integrasi"
        >
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-[var(--ui-fg-muted)]">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-1.5">
                    <x-ui.icon name="database" size="xs" /> 13 referensi
                </span>
                <span aria-hidden="true" class="text-[var(--ui-line-strong)]">→</span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] px-3 py-1.5">
                    <x-ui.icon name="school" size="xs" /> 17 operasional
                </span>
            </div>
        </x-page-header>

        @if(session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if(session('error'))
            <x-ui.alert type="danger">{{ session('error') }}</x-ui.alert>
        @endif
        @if($errors->any())
            <x-ui.alert type="danger">{{ $errors->first() }}</x-ui.alert>
        @endif

        @php($mirrorCentral = collect($status)->where('connection', 'central'))
        @php($mirrorSchool = collect($status)->where('connection', 'school'))
        @php($activeMirrorTab = in_array(request('mirror_tab'), ['referensi', 'sekolah'], true) ? request('mirror_tab') : 'referensi')

        <section aria-label="Ringkasan cakupan" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Total tabel</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-[var(--ui-fg-strong)]">{{ $mirrorCentral->count() + $mirrorSchool->count() }}</p>
                <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">{{ $mirrorCentral->count() }} pusat · {{ $mirrorSchool->count() }} sekolah</p>
            </div>
            <div class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Baris referensi</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-[var(--theme-content-accent)]">{{ number_format($mirrorCentral->sum('rows')) }}</p>
                <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">Database pusat</p>
            </div>
            <div class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Baris operasional</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-[var(--ui-fg-strong)]">{{ number_format($mirrorSchool->sum('rows')) }}</p>
                <p class="mt-0.5 text-xs text-[var(--ui-fg-muted)]">Sekolah aktif</p>
            </div>
            <div class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-4 py-3 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">Status lajur</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums text-[var(--ui-fg-strong)]" x-text="Math.max(refs.progress, school.progress) + '%'">0%</p>
                <p class="mt-0.5 truncate text-xs text-[var(--ui-fg-muted)]" x-text="school.message || refs.message || 'Belum pernah berjalan'"></p>
            </div>
        </section>

        <section aria-label="Lajur sinkronisasi" class="grid gap-4 lg:grid-cols-2">
            <article class="relative overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <span aria-hidden="true" class="pointer-events-none absolute -right-2 -top-6 select-none text-[6rem] font-extrabold leading-none text-[var(--ui-surface-muted)]">01</span>
                <div class="relative p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-[var(--theme-content-accent)]" style="background: color-mix(in srgb, var(--theme-accent) 12%, transparent);">
                                <x-ui.icon name="database" size="md" />
                            </span>
                            <div>
                                <h2 class="font-bold text-[var(--ui-fg-strong)]">Referensi</h2>
                                <p class="text-xs text-[var(--ui-fg-muted)]">13 tabel → database pusat · cukup sekali untuk sekolah pertama</p>
                            </div>
                        </div>
                        <span class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-[11px] font-bold" :class="refs.status === 'FAILED' ? 'border-rose-300 bg-rose-100 text-rose-800' : (refs.status === 'COMPLETED' ? 'border-emerald-300 bg-emerald-100 text-emerald-800' : 'border-[var(--ui-line)] bg-[var(--ui-surface-soft)] text-[var(--ui-fg-muted)]')" x-text="refs.status || 'Siaga'">Siaga</span>
                    </div>
                    <div class="mt-4">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="font-semibold text-[var(--ui-fg)]">Progres referensi</span>
                            <span class="tabular-nums text-[var(--ui-fg-muted)]" x-text="refsLabel"></span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-[var(--ui-surface-muted)]" role="progressbar" :aria-valuenow="refs.progress" aria-valuemin="0" aria-valuemax="100" aria-label="Progres sinkronisasi referensi">
                            <div class="h-full rounded-full bg-[var(--theme-action-bg)] transition-all" :style="'width: ' + refs.progress + '%'"></div>
                        </div>
                        <p class="mt-1 min-h-4 text-xs text-[var(--ui-fg-muted)]" x-text="refs.message"></p>
                    </div>
                    <form method="POST" action="{{ route('arkas.mirror.sync-refs') }}" data-confirm="Jalankan sinkronisasi referensi (13 tabel) ke database pusat? Cukup sekali untuk sekolah pertama." class="mt-3">
                        @csrf
                        <input type="hidden" name="confirm_sync" value="1">
                        <x-ui.button type="submit" variant="secondary" class="w-full" icon="sync">1. Sinkronisasi Referensi</x-ui.button>
                    </form>
                </div>
            </article>

            <article class="relative overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <span aria-hidden="true" class="pointer-events-none absolute -right-2 -top-6 select-none text-[6rem] font-extrabold leading-none text-[var(--ui-surface-muted)]">02</span>
                <div class="relative p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-[var(--theme-content-accent)]" style="background: color-mix(in srgb, var(--theme-accent) 12%, transparent);">
                                <x-ui.icon name="school" size="md" />
                            </span>
                            <div>
                                <h2 class="font-bold text-[var(--ui-fg-strong)]">Sekolah Aktif</h2>
                                <p class="text-xs text-[var(--ui-fg-muted)]">17 tabel → database sekolah · overlay SPJ dipertahankan</p>
                            </div>
                        </div>
                        <span class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-[11px] font-bold" :class="school.status === 'FAILED' ? 'border-rose-300 bg-rose-100 text-rose-800' : (school.status === 'COMPLETED' ? 'border-emerald-300 bg-emerald-100 text-emerald-800' : 'border-[var(--ui-line)] bg-[var(--ui-surface-soft)] text-[var(--ui-fg-muted)]')" x-text="school.status || 'Siaga'">Siaga</span>
                    </div>
                    <div class="mt-4">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="font-semibold text-[var(--ui-fg)]">Progres sekolah</span>
                            <span class="tabular-nums text-[var(--ui-fg-muted)]" x-text="schoolLabel"></span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-[var(--ui-surface-muted)]" role="progressbar" :aria-valuenow="school.progress" aria-valuemin="0" aria-valuemax="100" aria-label="Progres sinkronisasi sekolah">
                            <div class="h-full rounded-full bg-[var(--theme-action-bg)] transition-all" :style="'width: ' + school.progress + '%'"></div>
                        </div>
                        <p class="mt-1 min-h-4 text-xs text-[var(--ui-fg-muted)]" x-text="school.message"></p>
                    </div>
                    <form method="POST" action="{{ route('arkas.mirror.sync-school') }}" data-confirm="Jalankan sinkronisasi sekolah (17 tabel) untuk sekolah aktif? Data sumber ditimpa dari ARKAS, overlay SPJ dipertahankan." class="mt-3">
                        @csrf
                        <input type="hidden" name="confirm_sync" value="1">
                        <x-ui.button type="submit" class="w-full" icon="sync">2. Sinkronisasi Sekolah Aktif</x-ui.button>
                    </form>
                </div>
            </article>
        </section>

        <p class="text-xs leading-5 text-[var(--ui-fg-muted)]">Diperbarui otomatis tiap 2 detik selama proses berjalan. Sekolah pertama jalankan kedua lajur; sekolah berikutnya cukup Sinkronisasi Sekolah Aktif.</p>

        <section
            id="mirror-tables"
            class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow"
            @click="const btn = $event.target.closest('[data-tab]'); if (btn) selectMirrorTab(btn.dataset.tab)"
            @keydown="if ($event.key === 'ArrowRight' || $event.key === 'ArrowLeft') { const buttons = [...$el.querySelectorAll('[data-tab]')]; const current = buttons.indexOf(document.activeElement); if (current >= 0) { const next = $event.key === 'ArrowRight' ? (current + 1) % buttons.length : (current - 1 + buttons.length) % buttons.length; buttons[next].focus(); selectMirrorTab(buttons[next].dataset.tab); } }"
        >
            <x-tabs :tabs="[
                ['id' => 'referensi', 'label' => 'Referensi — Database Pusat', 'icon' => 'database', 'badge' => (string) $mirrorCentral->count()],
                ['id' => 'sekolah', 'label' => 'Operasional — Database Sekolah', 'icon' => 'school', 'badge' => (string) $mirrorSchool->count()],
            ]" :activeTab="$activeMirrorTab" />

            <div
                x-show="mirrorTab === 'referensi'"
                x-transition
                role="tabpanel"
                id="panel-referensi"
                aria-labelledby="tab-referensi"
                class="p-4 sm:p-5"
            >
                <p class="mb-3 text-sm text-[var(--ui-fg-muted)]">Referensi ARKAS di database pusat, dipakai bersama semua sekolah.</p>
                <x-ui.table pagination="auto" compact>
                    <thead>
                        <tr>
                            <th>Tabel ARKAS</th>
                            <th>Tabel Sinkronisasi</th>
                            <th>Label</th>
                            <th class="text-right">Baris</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mirrorCentral as $row)
                            <tr>
                                <td class="font-mono text-xs">{{ $row['source'] }}</td>
                                <td class="font-mono text-xs">{{ $row['mirror'] }}</td>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ number_format($row['rows']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>

            <div
                x-show="mirrorTab === 'sekolah'"
                x-cloak
                x-transition
                role="tabpanel"
                id="panel-sekolah"
                aria-labelledby="tab-sekolah"
                class="p-4 sm:p-5"
            >
                <p class="mb-3 text-sm text-[var(--ui-fg-muted)]">Data operasional ARKAS di database sekolah aktif. Tabel aplikasi cukup memanggil data ini, tidak menulis ulang kolom.</p>
                <x-ui.table pagination="auto" compact>
                    <thead>
                        <tr>
                            <th>Tabel ARKAS</th>
                            <th>Tabel Sinkronisasi</th>
                            <th>Label</th>
                            <th class="text-right">Baris</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mirrorSchool as $row)
                            <tr>
                                <td class="font-mono text-xs">{{ $row['source'] }}</td>
                                <td class="font-mono text-xs">{{ $row['mirror'] }}</td>
                                <td>{{ $row['label'] }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ number_format($row['rows']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
        </section>
    </div>

    <script>
        function sinkronisasiArkas() {
            return {
                mirrorTab: @json($activeMirrorTab),
                selectMirrorTab(name) {
                    if (name !== 'referensi' && name !== 'sekolah') return;
                    this.mirrorTab = name;
                    const url = new URL(window.location.href);
                    url.searchParams.set('mirror_tab', name);
                    window.history.replaceState({}, '', url);
                    document.querySelectorAll('#mirror-tables [data-tab]').forEach((btn) => {
                        const active = btn.dataset.tab === name;
                        btn.classList.toggle('ui-tab-active', active);
                        btn.setAttribute('aria-selected', active.toString());
                        btn.querySelectorAll('.ui-badge').forEach((badge) => {
                            badge.classList.toggle('ui-badge-theme', active);
                        });
                    });
                },
                refs: { progress: {{ (int) ($lastRefsRun?->progress ?? 0) }}, message: @json((string) ($lastRefsRun?->message ?? '')), status: @json((string) ($lastRefsRun?->status ?? '')) },
                school: { progress: {{ (int) ($lastRun?->progress ?? 0) }}, message: @json((string) ($lastRun?->message ?? '')), status: @json((string) ($lastRun?->status ?? '')) },
                timer: null,
                get refsLabel() { return this.refs.status ? this.refs.status + ' · ' + this.refs.progress + '%' : 'Belum pernah berjalan'; },
                get schoolLabel() { return this.school.status ? this.school.status + ' · ' + this.school.progress + '%' : 'Belum pernah berjalan'; },
                init() { this.poll(); this.timer = setInterval(() => this.poll(), 2000); },
                poll() {
                    fetch(@json(route('arkas.mirror.status')), { headers: { 'Accept': 'application/json' } })
                        .then((response) => response.json())
                        .then((data) => {
                            if (data.refs) { this.refs = data.refs; }
                            if (data.school) { this.school = data.school; }
                            if (this.finished(this.refs) && this.finished(this.school) && this.timer) {
                                clearInterval(this.timer);
                                this.timer = null;
                            }
                        })
                        .catch(() => {});
                },
                finished(entry) { return !entry.status || entry.status === 'COMPLETED' || entry.status === 'FAILED'; },
            };
        }
    </script>
</x-layouts.tailwind-app>
