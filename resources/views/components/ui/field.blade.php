@props([
    'label' => null,
    'for' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'id' => null,
])

{{-- Bila pemanggil tidak mengoper `for`, generate id laluaitkan ke kontrol
     pertama di dalam slot. Ini menutup celah aksesibilitas yang ditemukan pada
     browser QA 2026-10-05: label terlihat tetapi klik label tidak memfokuskan
     input dan screen reader tidak membacakan kontrol. --}}
@php
    $controlId = $for ?: $id;
    $needsGeneratedId = $label !== null && $controlId === null;
    if ($needsGeneratedId) {
        static $fieldSequence = 0;
        $fieldSequence++;
        $controlId = 'ui-field-'.$fieldSequence;
    }
@endphp

<div {{ $attributes->class(['space-y-1.5']) }}>
    @if ($label)
        <label @if ($controlId) for="{{ $controlId }}" @endif
            class="block text-sm font-semibold text-[var(--ui-fg-strong)]">
            {{ $label }}
            @if ($required)
                <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only"> wajib</span>
            @endif
        </label>
    @endif

    @if ($needsGeneratedId)
        <span data-ui-field-bind-id="{{ $controlId }}" hidden aria-hidden="true"></span>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="text-xs font-medium text-rose-600">{{ $error }}</p>
    @elseif($hint)
        <p class="text-xs leading-5 text-[var(--ui-fg-muted)]">{{ $hint }}</p>
    @endif
</div>
