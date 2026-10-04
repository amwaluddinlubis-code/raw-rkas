{{-- Navigasi antar paket (← Prev / Next →) dalam tenant aktif yang sama (sekolah + tahun + sumber dana).
     Dipakai di tab Rincian, Rincian Pajak, dan Penomoran. Tab Isian Manual memakai markup sendiri
     karena tombol Simpan berada di tengah (lihat spj/index.blade.php) — bila mengubah gaya tombol,
     sinkronkan kedua tempat.
     $packageTab: nama tab internal aktif agar navigasi tetap di tab yang sama. --}}
@php($navigationTab = $packageTab ?? 'rincian')
@if($previousPackageId ?? null)
    <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'paket', 'package_id' => $previousPackageId, 'package_tab' => $navigationTab])" title="Buka paket sebelumnya pada tahun anggaran dan sumber dana aktif" class="justify-center">← Prev</x-ui.button>
@else
    <x-ui.button variant="secondary" disabled title="Tidak ada paket sebelumnya pada tahun anggaran dan sumber dana aktif" class="justify-center opacity-55">← Prev</x-ui.button>
@endif
@if($nextPackageId ?? null)
    <x-ui.button variant="secondary" :href="route('spj.index', ['tab' => 'paket', 'package_id' => $nextPackageId, 'package_tab' => $navigationTab])" title="Buka paket berikutnya pada tahun anggaran dan sumber dana aktif" class="justify-center">Next →</x-ui.button>
@else
    <x-ui.button variant="secondary" disabled title="Tidak ada paket berikutnya pada tahun anggaran dan sumber dana aktif" class="justify-center opacity-55">Next →</x-ui.button>
@endif
