<x-layouts.tailwind-app>
    <div class="flex flex-col gap-6">
        <x-page-header title="Tinjau Identitas Pegawai" subtitle="Nama pegawai yang sama pada feed berbeda tetapi tidak punya identitas nasional pelengkap untuk fusi otomatis. Keputusan penggabungan/pemisahan tetap di operator." kicker="Pegawai" />
        @if ($groups->isEmpty())
            <section class="rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-800">Tidak ada grup identitas ambigu. Setiap nama ternormalisasi unik atau sudah difusi otomatis.</section>
        @else
            @foreach ($groups as $group)
                <section class="rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow-sm">
                    <h2 class="text-sm font-bold text-[var(--ui-fg-strong)]">"{{ $group['key'] }}" — {{ count($group['members']) }} entri</h2>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead><tr class="text-[var(--ui-fg-muted)]"><th class="px-2 py-1 text-left">Nama</th><th class="px-2 py-1 text-left">Sumber</th><th class="px-2 py-1 text-left">NUPTK</th><th class="px-2 py-1 text-left">NIP</th><th class="px-2 py-1 text-left">NIK</th><th class="px-2 py-1 text-left">Dapodik ID</th><th class="px-2 py-1 text-left">Operator</th></tr></thead>
                            <tbody>
                                @foreach ($group['members'] as $m)
                                    <tr class="border-t border-[var(--ui-line)]">
                                        <td class="px-2 py-1">{{ $m['name'] }}</td>
                                        <td class="px-2 py-1">{{ $m['source_type'] ?: '—' }}</td>
                                        <td class="px-2 py-1">{{ $m['nuptk'] ?: '—' }}</td>
                                        <td class="px-2 py-1">{{ $m['nip'] ?: '—' }}</td>
                                        <td class="px-2 py-1">{{ $m['nik'] ?: '—' }}</td>
                                        <td class="px-2 py-1">{{ $m['dapodik_id'] ?: '—' }}</td>
                                        <td class="px-2 py-1">{{ $m['operator_locked'] ? 'Terkunci' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[11px] text-[var(--ui-fg-muted)]">Gabungkan hanya jika bukti nasional melengkapi (mis. satu PEGAWAI hanya NUPTK + satu PTK hanya NIP). Jika beda orang, lengkapi identitasnya di sumber ARKAS/Dapodik.</p>
                </section>
            @endforeach
        @endif
    </div>
</x-layouts.tailwind-app>
