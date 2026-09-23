<x-layouts.public-tailwind title="Setup Awal · SPJ BOSP" :wide="true">
    <div class="relative overflow-hidden rounded-2xl border border-[var(--ui-line)] p-5 sm:p-6"
        style="background: radial-gradient(130% 150% at 90% 0%, color-mix(in srgb, var(--theme-accent) 38%, transparent), transparent 62%), radial-gradient(130% 160% at 100% 100%, color-mix(in srgb, #8b5cf6 28%, transparent), transparent 58%), linear-gradient(120deg, color-mix(in srgb, var(--theme-accent) 14%, transparent), transparent 55%), linear-gradient(135deg, color-mix(in srgb, var(--theme-accent-soft) 88%, transparent), transparent 80%), var(--ui-surface-soft); box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12)); display: flex; flex-wrap: wrap; align-items: center; column-gap: 1.25rem; row-gap: 1.25rem;">
        <div style="flex: 1 1 300px; min-width: 0;">
        <p class="inline-flex items-center gap-2 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-1 text-[11px] font-bold uppercase tracking-[.14em] text-[var(--theme-content-accent)]">
            <x-ui.icon name="settings" size="xs" /> Pengaturan pertama
        </p>
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-[var(--ui-fg-strong)] sm:text-3xl" data-page-header-raw>Selamat datang di SPJ BOSP</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--ui-fg-muted)]">Tiga langkah cepat: isi profil
            sekolah, buat akun administrator, lalu masuk dan pilih tahun anggaran untuk mulai mengelola data BOSP
            dan SPJ.</p>
        <ol class="mt-4 flex flex-wrap items-center gap-2 text-xs font-bold" aria-label="Langkah pengaturan">
            <li class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] px-3 py-1.5 text-[var(--ui-fg-strong)]" style="background: radial-gradient(130% 150% at 85% 0%, color-mix(in srgb, var(--theme-accent) 24%, transparent), transparent 65%), color-mix(in srgb, var(--theme-accent-soft) 70%, var(--ui-surface-base));">
                <span aria-hidden="true">1</span> Profil sekolah
            </li>
            <li class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-1.5 text-[var(--ui-fg-muted)]">
                <span aria-hidden="true">2</span> Akun administrator
            </li>
            <li class="inline-flex items-center gap-1.5 rounded-full border border-[var(--ui-line)] bg-[var(--ui-surface-base)] px-3 py-1.5 text-[var(--ui-fg-muted)]">
                <span aria-hidden="true">3</span> Masuk &amp; pilih tahun
            </li>
        </ol>
        </div>
        <span class="page-header-logo page-header-logo-lg" style="margin-inline-start: auto;" aria-hidden="true"><img src="{{ asset('images/logo-app-spj.png') }}" alt="" style="height: 7.5rem; width: 7.5rem; border-radius: 9999px; background: #ffffff; object-fit: contain;"></span>
    </div>

    <form method="POST" action="{{ route('setup.store') }}" class="mt-6 space-y-5">
        @csrf

        <div class="grid items-start gap-5 lg:grid-cols-2">
        <section aria-label="Profil sekolah" class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 sm:p-5"
            style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--theme-accent-soft)] text-sm font-bold text-[var(--theme-content-accent)]">1</span>
                <div>
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Profil sekolah</h2>
                    <p class="mt-1 text-xs leading-5 text-[var(--ui-fg-muted)]">Identitas utama workspace sekolah.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3">
                <div class="grid gap-3 sm:grid-cols-3">
                <x-ui.field :error="$errors->first('school_code')">
                    <x-ui.input id="school-code" name="school_code" value="{{ old('school_code') }}" maxlength="40"
                        pattern="[A-Za-z0-9._-]+" autocomplete="organization" required placeholder="Kode Sekolah *"
                        aria-label="Kode Sekolah, wajib diisi" />
                </x-ui.field>
                <x-ui.field :error="$errors->first('npsn')">
                    <x-ui.input id="npsn" name="npsn" value="{{ old('npsn') }}" maxlength="16"
                        inputmode="numeric" autocomplete="off" required placeholder="NPSN *"
                        aria-label="NPSN, wajib diisi" />
                </x-ui.field>
                <x-ui.field :error="$errors->first('year')">
                    <x-ui.input id="setup-year" type="number" name="year" value="{{ old('year', date('Y')) }}"
                        min="2020" max="2100" inputmode="numeric" required placeholder="Tahun Anggaran *"
                        aria-label="Tahun Anggaran Awal, wajib diisi" />
                </x-ui.field>
                </div>
                <div class="grid gap-3">
                <x-ui.field :error="$errors->first('school_name')">
                    <x-ui.input id="school-name" name="school_name" value="{{ old('school_name') }}"
                        autocomplete="organization" required placeholder="Nama Sekolah *"
                        aria-label="Nama Sekolah, wajib diisi" />
                </x-ui.field>
                <x-ui.field :error="$errors->first('address')">
                    <x-ui.input id="school-address" name="address" value="{{ old('address') }}"
                        autocomplete="street-address" placeholder="Alamat Sekolah" aria-label="Alamat Sekolah" />
                </x-ui.field>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field :error="$errors->first('desa')">
                    <x-ui.input id="school-desa" name="desa" value="{{ old('desa') }}" placeholder="Desa/Kelurahan"
                        aria-label="Desa atau Kelurahan" />
                </x-ui.field>
                <x-ui.field :error="$errors->first('district')">
                    <x-ui.input id="school-district" name="district" value="{{ old('district') }}" placeholder="Kecamatan"
                        aria-label="Kecamatan" />
                </x-ui.field>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.field :error="$errors->first('regency')">
                    <x-ui.input id="school-regency" name="regency" value="{{ old('regency') }}" placeholder="Kabupaten/Kota"
                        aria-label="Kabupaten atau Kota" />
                </x-ui.field>
                <x-ui.field :error="$errors->first('province')">
                    <x-ui.input id="school-province" name="province" value="{{ old('province') }}" placeholder="Provinsi"
                        aria-label="Provinsi" />
                </x-ui.field>
                </div>
            </div>
        </section>

        <section aria-label="Akun administrator" class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-soft)] p-4 sm:p-5"
            style="box-shadow: var(--profile-floating-shadow, 0 20px 45px rgb(15 23 42 / .12));">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--theme-accent-soft)] text-sm font-bold text-[var(--theme-content-accent)]">2</span>
                <div>
                    <h2 class="font-bold text-[var(--ui-fg-strong)]">Akun administrator</h2>
                    <p class="mt-1 text-xs leading-5 text-[var(--ui-fg-muted)]">Untuk masuk dan mengatur aplikasi.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                <x-ui.field :error="$errors->first('admin_name')">
                    <x-ui.input id="admin-name" name="admin_name" value="{{ old('admin_name') }}" autocomplete="name"
                        required placeholder="Nama Administrator *" aria-label="Nama Administrator, wajib diisi" />
                </x-ui.field>
                <x-ui.field :error="$errors->first('email')">
                    <x-ui.input id="admin-email" type="email" name="email" value="{{ old('email') }}"
                        autocomplete="email" required placeholder="Email *" aria-label="Email, wajib diisi" />
                </x-ui.field>
                <x-ui.field hint="Minimal 12 karakter." :error="$errors->first('password')">
                    <div class="relative">
                    <x-ui.input id="admin-password" type="password" name="password" autocomplete="new-password"
                        minlength="12" required placeholder="Kata Sandi *" class="pr-11"
                        aria-label="Kata Sandi, minimal 12 karakter, wajib diisi" />
                    <button type="button" data-password-toggle="admin-password" aria-pressed="false" aria-label="Tampilkan kata sandi"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-[10px] text-[var(--ui-fg-muted)] transition hover:text-[var(--ui-fg-strong)]">
                        <span data-icon-show><x-ui.icon name="eye" size="sm" /></span>
                        <span data-icon-hide class="hidden"><x-ui.icon name="eye-off" size="sm" /></span>
                    </button>
                    </div>
                </x-ui.field>
                <x-ui.field :error="$errors->first('password_confirmation')">
                    <div class="relative">
                    <x-ui.input id="password-confirmation" type="password" name="password_confirmation"
                        autocomplete="new-password" minlength="12" required placeholder="Ulangi Kata Sandi *" class="pr-11"
                        aria-label="Ulangi Kata Sandi, wajib diisi" />
                    <button type="button" data-password-toggle="password-confirmation" aria-pressed="false" aria-label="Tampilkan konfirmasi kata sandi"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-[10px] text-[var(--ui-fg-muted)] transition hover:text-[var(--ui-fg-strong)]">
                        <span data-icon-show><x-ui.icon name="eye" size="sm" /></span>
                        <span data-icon-hide class="hidden"><x-ui.icon name="eye-off" size="sm" /></span>
                    </button>
                    </div>
                </x-ui.field>
            </div>
        </section>
        </div>

        <div
            class="flex flex-col gap-3 border-t border-[var(--ui-line)] pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs leading-5 text-[var(--ui-fg-muted)]">Setelah disimpan, Anda akan diarahkan untuk memilih
                tahun aktif.</p>
            <x-ui.button type="submit" icon="check" class="w-full justify-center sm:w-auto">Simpan dan Masuk</x-ui.button>
        </div>
    </form>

    <script>
        (() => {
            document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const input = document.getElementById(btn.dataset.passwordToggle);
                    if (!(input instanceof HTMLInputElement)) return;
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    btn.setAttribute('aria-pressed', show.toString());
                    btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
                    btn.querySelector('[data-icon-show]')?.classList.toggle('hidden', show);
                    btn.querySelector('[data-icon-hide]')?.classList.toggle('hidden', !show);
                });
            });
        })();
    </script>
</x-layouts.public-tailwind>
