@props([
    'name' => null,
    'id' => null,
    'accept' => null,
    'required' => false,
    'disabled' => false,
])

{{-- Primitif unggah file bertema: tombol pilih file mengikuti token tema
    (accent-soft) agar konsisten di seluruh form pengaturan/dokumen. --}}
<input
    type="file"
    @if($name) name="{{ $name }}" @endif
    @if($id) id="{{ $id }}" @endif
    @if($accept) accept="{{ $accept }}" @endif
    @required($required)
    @disabled($disabled)
    {{ $attributes->class([
        'block w-full rounded-lg border border-[var(--ui-line-strong)] bg-[var(--ui-surface-base)] px-3 py-2 text-sm text-[var(--ui-fg)] file:mr-3 file:rounded-md file:border-0 file:bg-[var(--theme-accent-soft)] file:px-3 file:py-2 file:text-xs file:font-bold file:text-[var(--theme-content-accent)]',
    ]) }}
>
