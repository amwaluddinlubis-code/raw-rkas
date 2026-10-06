@props([
    'panel',
    'controls',
    'state' => 'open',
])

<button type="button" class="ui-btn ui-btn-secondary !min-h-0 !px-3 !py-1.5 text-xs"
    @click="{{ $state }} = !{{ $state }}" :aria-expanded="{{ $state }}.toString()"
    aria-controls="{{ $controls }}"
    :aria-label="({{ $state }} ? 'Tutup panel ' : 'Buka panel ') + @js($panel)">
    <span x-text="{{ $state }} ? 'Tutup panel' : 'Buka panel'"></span>
    <x-ui.icon name="chevron-down" size="xs" ::class="{{ $state }} ? 'rotate-180' : ''" />
</button>