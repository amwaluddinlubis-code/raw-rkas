<x-layouts.public-tailwind title="Pilih Sekolah · SPJ BOSP" :wide="true">
    <div class="context-keep-bg relative overflow-hidden rounded-2xl border border-[var(--ui-line)] p-5 sm:p-6"
        style="background: radial-gradient(130% 150% at 90% 0%, color-mix(in srgb, var(--theme-accent) 38%, transparent), transparent 62%), radial-gradient(130% 160% at 100% 100%, color-mix(in srgb, #8b5cf6 28%, transparent), transparent 58%), linear-gradient(120deg, color-mix(in srgb, var(--theme-accent) 14%, transparent), transparent 55%), linear-gradient(135deg, color-mix(in srgb, var(--theme-accent-soft) 88%, transparent), transparent 80%), var(--ui-surface-soft); box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12)); display: flex; flex-wrap: wrap; align-items: center; column-gap: 1.25rem; row-gap: 1.25rem;">
        <div style="flex: 1 1 300px; min-width: 0;">
        <p class="inline-flex items-center gap-2 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-1 text-[11px] font-bold uppercase tracking-[.14em] text-[var(--theme-content-accent)]">
            <x-ui.icon name="school" size="xs" /> Langkah 1 dari 2
        </p>
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-[var(--ui-fg-strong)] sm:text-3xl" data-page-header-raw>Pilih Sekolah</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--ui-fg-muted)]">Pilih sekolah yang akan dikelola.
            Seluruh data nanti terisolasi per sekolah, tahun anggaran, dan sumber dana.</p>
        <ol class="mt-4 flex flex-wrap items-center gap-2 text-xs font-bold" aria-label="Langkah pengaturan">
            <li class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] px-3 py-1.5 text-[var(--ui-fg-strong)]" style="background: radial-gradient(130% 150% at 85% 0%, color-mix(in srgb, var(--theme-accent) 24%, transparent), transparent 65%), color-mix(in srgb, var(--theme-accent-soft) 70%, var(--ui-surface-base));">
                <span aria-hidden="true">1</span> Sekolah
            </li>
            <li class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-1.5 text-[var(--ui-fg-muted)]">
                <span aria-hidden="true">2</span> Konteks kerja
            </li>
        </ol>
        </div>
        <span class="page-header-logo page-header-logo-lg" style="margin-inline-start: auto;" aria-hidden="true"><img src="{{ asset('images/logo-app-spj.png') }}" alt="" style="height: 7.5rem; width: 7.5rem; border-radius: 9999px; background: #ffffff; object-fit: contain;"></span>
    </div>

    <div class="mt-6 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
        <section aria-label="Daftar sekolah"
            class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 sm:p-5"
            style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
            <livewire:school-selector />
        </section>
        <aside class="grid gap-4" aria-label="Panduan memilih sekolah">
            <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4"
                style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
                <div class="flex items-center gap-2 text-[var(--theme-content-accent)]">
                    <x-ui.icon name="search" size="sm" />
                    <h2 class="text-xs font-bold uppercase tracking-wide">Cara mencari</h2>
                </div>
                <p class="mt-2 text-xs leading-5 text-[var(--ui-fg-muted)]">Ketik nama sekolah atau NPSN pada kolom
                    pencarian. Daftar menyaring otomatis saat Anda mengetik.</p>
            </section>
            <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4"
                style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
                <div class="flex items-center gap-2 text-[var(--theme-content-accent)]">
                    <x-ui.icon name="lock" size="sm" />
                    <h2 class="text-xs font-bold uppercase tracking-wide">Data terisolasi</h2>
                </div>
                <p class="mt-2 text-xs leading-5 text-[var(--ui-fg-muted)]">Setiap sekolah memakai database sendiri.
                    Pilihan ini hanya menentukan sekolah aktif, tanpa mengubah data sekolah lain.</p>
            </section>
        </aside>
    </div>
</x-layouts.public-tailwind>
