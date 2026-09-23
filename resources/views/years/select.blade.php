<x-layouts.public-tailwind title="Pilih Tahun dan Sumber Dana" :wide="true">
    <div class="context-keep-bg relative overflow-hidden rounded-2xl border border-[var(--ui-line)] p-5 sm:p-6"
        style="background: radial-gradient(130% 150% at 90% 0%, color-mix(in srgb, var(--theme-accent) 38%, transparent), transparent 62%), radial-gradient(130% 160% at 100% 100%, color-mix(in srgb, #8b5cf6 28%, transparent), transparent 58%), linear-gradient(120deg, color-mix(in srgb, var(--theme-accent) 14%, transparent), transparent 55%), linear-gradient(135deg, color-mix(in srgb, var(--theme-accent-soft) 88%, transparent), transparent 80%), var(--ui-surface-soft); box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12)); display: flex; flex-wrap: wrap; align-items: center; column-gap: 1.25rem; row-gap: 1.25rem;">
        <div style="flex: 1 1 300px; min-width: 0;">
        <p class="inline-flex items-center gap-2 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-1 text-[11px] font-bold uppercase tracking-[.14em] text-[var(--theme-content-accent)]">
            <x-ui.icon name="calendar" size="xs" /> Langkah 2 dari 2
        </p>
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-[var(--ui-fg-strong)] sm:text-3xl" data-page-header-raw>Tentukan Konteks Kerja Anda</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--ui-fg-muted)]">Hubungkan ARKAS bila perlu, lalu pilih
            tahun anggaran dan sumber dana untuk mulai bekerja.</p>
        <ol class="mt-4 flex flex-wrap items-center gap-2 text-xs font-bold" aria-label="Langkah pengaturan">
            <li class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-1.5 text-[var(--ui-fg-muted)]">
                <x-ui.icon name="check" size="xs" /><span aria-hidden="true" class="sr-only">Selesai:</span> Sekolah
            </li>
            <li class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] px-3 py-1.5 text-[var(--ui-fg-strong)]" style="background: radial-gradient(130% 150% at 85% 0%, color-mix(in srgb, var(--theme-accent) 24%, transparent), transparent 65%), color-mix(in srgb, var(--theme-accent-soft) 70%, var(--ui-surface-base));">
                <span aria-hidden="true">2</span> Konteks kerja
            </li>
        </ol>
        </div>
        <span class="page-header-logo page-header-logo-lg" style="margin-inline-start: auto;" aria-hidden="true"><img src="{{ asset('images/logo-app-spj.png') }}" alt="" style="height: 7.5rem; width: 7.5rem; border-radius: 9999px; background: #ffffff; object-fit: contain;"></span>
    </div>

    <section aria-label="Sekolah aktif"
        class="mt-6 flex items-center gap-3 rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4"
        style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--ui-surface-muted)] text-[var(--ui-fg-muted)]"
            aria-hidden="true"><x-ui.icon name="school" size="sm" /></div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-lg font-semibold text-[var(--ui-fg-strong)]">{{ $school->name }}</p>
            <p class="mt-0.5 text-sm text-[var(--ui-fg-muted)]">NPSN {{ $school->npsn }}</p>
        </div>
        <a href="{{ route('schools.select') }}"
            class="shrink-0 text-sm font-medium text-[var(--ui-fg-muted)] underline-offset-4 transition hover:text-[var(--theme-content-accent)] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--theme-accent)]">Ubah
            Sekolah</a>
    </section>

    <div class="mt-6 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="grid gap-5">
        @unless ($hasFundSourceContext)
            <div
                class="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                <x-ui.icon name="warning" class="mt-0.5 shrink-0" />
                <p>Database sekolah belum siap untuk konteks sumber dana. Buka <a
                        href="{{ route('database-manager.index') }}"
                        class="font-bold underline underline-offset-2">Manajemen Database</a> lalu jalankan migrasi pada
                    sekolah aktif.</p>
            </div>
        @endunless
        @unless ($arkasSource)
            <x-ui.form-section title="Hubungkan ARKAS terlebih dahulu"
                description="Simpan lokasi sumber ARKAS agar tahun anggaran dan sumber dana dapat disinkronkan.">
                <form method="POST" action="{{ route('arkas.settings.store') }}" class="grid gap-4">
                    @csrf<input type="hidden" name="school_id" value="{{ $school->id }}"><input type="hidden"
                        name="return_to" value="{{ route('years.select') }}">
                    <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Lokasi database ARKAS" for="year-arkas-database"
                        hint="Gunakan file database ARKAS sekolah, bukan database SPJ lokal." :error="$errors->first('database_path')"
                        required><x-ui.input id="year-arkas-database" name="database_path" :value="old('database_path')"
                            placeholder="D:\Folder ARKAS\database_arkas.db" class="font-mono" required /></x-ui.field>
                    <x-ui.field label="Lokasi ARKASBridge.exe" for="year-arkas-bridge"
                        hint="Engine untuk membaca database ARKAS." :error="$errors->first('bridge_path')" required><x-ui.input
                            id="year-arkas-bridge" name="bridge_path" :value="old('bridge_path', $defaultBridgePath)" class="font-mono"
                            required /></x-ui.field>
                    </div>
                    <div class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <x-ui.field label="Kata sandi database ARKAS" for="year-arkas-password"
                        hint="Wajib diisi saat konfigurasi pertama." :error="$errors->first('database_password')"><x-ui.input id="year-arkas-password"
                            type="password" name="database_password" autocomplete="new-password"
                            placeholder="Masukkan kata sandi database" /></x-ui.field>
                    <div class="flex sm:justify-end"><x-ui.button type="submit" icon="save"
                            class="w-full justify-center sm:w-auto">Simpan pengaturan ARKAS</x-ui.button></div>
                    </div>
                </form>
            </x-ui.form-section>
        @endunless
        <livewire:year-selector :has-fund-source-context="$hasFundSourceContext" />
        </div>
        <aside class="grid gap-4" aria-label="Panduan konteks kerja">
            <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4"
                style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
                <div class="flex items-center gap-2 text-[var(--theme-content-accent)]">
                    <x-ui.icon name="calendar" size="sm" />
                    <h2 class="text-xs font-bold uppercase tracking-wide">Alur konteks</h2>
                </div>
                <ol class="mt-2 grid gap-2 text-xs leading-5 text-[var(--ui-fg-muted)]">
                    <li class="flex gap-2"><span class="font-extrabold text-[var(--theme-content-accent)]">1.</span> Hubungkan ARKAS bila diminta.</li>
                    <li class="flex gap-2"><span class="font-extrabold text-[var(--theme-content-accent)]">2.</span> Pilih tahun anggaran dan sumber dana.</li>
                    <li class="flex gap-2"><span class="font-extrabold text-[var(--theme-content-accent)]">3.</span> Tekan Mulai Bekerja.</li>
                </ol>
            </section>
            <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4"
                style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
                <div class="flex items-center gap-2 text-[var(--theme-content-accent)]">
                    <x-ui.icon name="lock" size="sm" />
                    <h2 class="text-xs font-bold uppercase tracking-wide">Bisa diganti</h2>
                </div>
                <p class="mt-2 text-xs leading-5 text-[var(--ui-fg-muted)]">Konteks ini menyaring seluruh data
                    aplikasi dan dapat diganti kapan saja dari bilah atas.</p>
            </section>
        </aside>
    </div>
</x-layouts.public-tailwind>
