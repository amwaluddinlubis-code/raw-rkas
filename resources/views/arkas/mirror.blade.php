<x-layouts.tailwind-app>
    <div class="space-y-6" x-data="sinkronisasiArkas()" x-init="init()">
        <x-page-header
            title="Pusat Sinkronisasi ARKAS"
            subtitle="Dua lajur tetap: 13 tabel referensi mengalir ke database pusat, 17 tabel operasional mengalir ke database sekolah aktif. Tanpa mapping manual, overlay SPJ selalu dipertahankan."
            kicker="Data & Integrasi"
        >
            <div class="grid divide-y divide-[var(--ui-line)] sm:grid-cols-2 xl:grid-cols-4 sm:divide-x sm:divide-y-0">
                <x-stat-item label="Total tabel" :value="number_format($mirrorCentral->count() + $mirrorSchool->count(), 0, ',', '.')" :hint="$mirrorCentral->count().' pusat · '.$mirrorSchool->count().' sekolah'" icon="database" />
                <x-stat-item label="Baris referensi" :value="number_format($mirrorCentral->sum('rows'), 0, ',', '.')" hint="Database pusat" icon="report" value-class="text-[var(--theme-content-accent)]" />
                <x-stat-item label="Baris operasional" :value="number_format($mirrorSchool->sum('rows'), 0, ',', '.')" hint="Sekolah aktif" icon="school" />
                <x-stat-item label="Status lajur" :value="(string) (max((int) ($lastRefsRun?->progress ?? 0), (int) ($lastRun?->progress ?? 0))).'%'" :hint="$lastRun?->message ?: ($lastRefsRun?->message ?: 'Belum pernah berjalan')" icon="refresh" />
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
                        <p class="mt-1 text-xs text-[var(--ui-fg-muted)]" x-text="refs.finished_at ? 'Selesai: ' + refs.finished_at : ''"></p>
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
                        <p class="mt-1 text-xs text-[var(--ui-fg-muted)]" x-text="school.finished_at ? 'Selesai: ' + school.finished_at : ''"></p>
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

        @if($mirrorHealth !== null)
            <section aria-label="Kesehatan mirror kas" class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow-sm">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-[var(--theme-content-accent)]" style="background: color-mix(in srgb, var(--theme-accent) 12%, transparent);">
                                <x-ui.icon name="audit" size="md" />
                            </span>
                            <div>
                                <h2 class="font-bold text-[var(--ui-fg-strong)]">Kesehatan Mirror Kas</h2>
                                <p class="text-xs text-[var(--ui-fg-muted)]">Mirror kas_umum vs BKU per tahun anggaran dan sumber dana · sekolah aktif</p>
                            </div>
                        </div>
                        @if($mirrorHealth['ok'])
                            <span class="inline-flex shrink-0 items-center rounded-full border border-emerald-300 bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Sehat</span>
                        @else
                            <span class="inline-flex shrink-0 items-center rounded-full border border-rose-300 bg-rose-100 px-2.5 py-1 text-[11px] font-bold text-rose-800">Bermasalah</span>
                        @endif
                    </div>
                    <div class="mt-4">
                        <form method="GET" action="{{ route('arkas.mirror') }}" class="mb-3 flex flex-wrap items-end gap-3">
                            @if(request('mirror_tab'))
                                <input type="hidden" name="mirror_tab" value="{{ request('mirror_tab') }}">
                            @endif
                            <x-ui.field label="Tahun" for="health-year">
                                <x-ui.select id="health-year" name="health_year" data-auto-submit="true">
                                    <option value="">Semua tahun</option>
                                    @foreach(array_keys($healthYears ?? []) as $year)
                                        <option value="{{ $year }}" @selected((string) ($healthYear ?? '') === (string) $year)>TA {{ $year }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field label="Kategori" for="health-category">
                                <x-ui.select id="health-category" name="health_category" data-auto-submit="true">
                                    <option value="">Semua kategori</option>
                                    @foreach(array_keys($healthCategories ?? []) as $category)
                                        <option value="{{ $category }}" @selected((string) ($healthCategory ?? '') === (string) $category)>{{ $category === 'TANPA_KATEGORI' ? '—' : $category }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        </form>
                        <x-ui.table pagination="auto" compact>
                            <thead>
                                <tr>
                                    <th>Tahun</th>
                                    <th>Dana</th>
                                    <th>Kategori</th>
                                    <th class="text-right">Mirror (baris)</th>
                                    <th class="text-right">Mirror (Rp)</th>
                                    <th class="text-right">BKU (baris)</th>
                                    <th class="text-right">BKU (Rp)</th>
                                    <th class="text-right">Basi (baris)</th>
                                    <th class="text-right">Basi (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($mirrorHealth['scopes'] ?? [] as $scope)
                                    <tr>
                                        <td class="tabular-nums">{{ $scope['year'] }}</td>
                                        <td class="tabular-nums">{{ $scope['fund'] }}</td>
                                        <td>{{ $scope['category'] === 'TANPA_KATEGORI' ? '—' : $scope['category'] }}</td>
                                        <td class="text-right tabular-nums">{{ number_format($scope['mirror_n']) }}</td>
                                        <td class="text-right tabular-nums">{{ number_format($scope['mirror_sum'], 0, ',', '.') }}</td>
                                        <td class="text-right tabular-nums">{{ number_format($scope['bku_n']) }}</td>
                                        <td class="text-right tabular-nums">{{ number_format($scope['bku_sum'], 0, ',', '.') }}</td>
                                        <td class="text-right font-semibold tabular-nums">{{ number_format($scope['stale_n']) }}</td>
                                        <td class="text-right font-semibold tabular-nums">{{ number_format($scope['stale_sum'], 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-[var(--ui-fg-muted)]">Belum ada kas terscope pada sekolah aktif.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-ui.table>
                    </div>
                    @if(($mirrorHealth['unscoped_n'] ?? 0) > 0)
                        <p class="mt-2 text-xs text-[var(--ui-fg-muted)]">{{ number_format($mirrorHealth['unscoped_n']) }} baris tanpa tahun/dana teridentifikasi — dilaporkan saja, tidak tersentuh repair.</p>
                    @endif
                    @if(($healthStaleTotal ?? 0) > 0)
                        <form method="POST" action="{{ route('arkas.mirror.health-repair') }}" data-confirm="Hapus {{ number_format($healthStaleTotal) }} baris mirror kas basi pada sekolah aktif (seluruh tahun dan kategori)? Database dibackup otomatis terlebih dahulu." class="mt-3">
                            @csrf
                            <input type="hidden" name="confirm_sync" value="1">
                            <x-ui.button type="submit" variant="danger" class="w-full" icon="trash">Bersihkan {{ number_format($healthStaleTotal) }} baris basi (semua filter)</x-ui.button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

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
                refs: { progress: {{ (int) ($lastRefsRun?->progress ?? 0) }}, message: @json((string) ($lastRefsRun?->message ?? '')), status: @json((string) ($lastRefsRun?->status ?? '')), finished_at: @json($lastRefsRun?->finished_at?->translatedFormat('d M Y H:i')) },
                school: { progress: {{ (int) ($lastRun?->progress ?? 0) }}, message: @json((string) ($lastRun?->message ?? '')), status: @json((string) ($lastRun?->status ?? '')), finished_at: @json($lastRun?->finished_at?->translatedFormat('d M Y H:i')) },
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
