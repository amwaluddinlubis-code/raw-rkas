@props([
    'title',
    'description' => null,
    'collapsible' => false,
    'open' => true,
    'panelId' => null,
])

@php
    $resolvedPanelId = $panelId ?? 'ui-form-section-' . \Illuminate\Support\Str::slug($title);
@endphp

<section
    @if($collapsible)
        x-data="{ open: @js($open) }"
    @endif
    {{ $attributes->class(['ui-form-section']) }}
>
    <div class="ui-form-section-header">
        <div class="ui-form-section-copy">
            <h2 class="ui-form-section-title">{{ $title }}</h2>
            @if($description)<p class="ui-form-section-description">{{ $description }}</p>@endif
        </div>
        <div class="ui-form-section-actions">
            @if(isset($actions)){{ $actions }}@endif
            @if($collapsible)
                <x-ui.panel-toggle :panel="$title" :controls="$resolvedPanelId" />
            @endif
        </div>
    </div>
    <div class="ui-form-section-body" @if($collapsible) id="{{ $resolvedPanelId }}" x-show="open" @endif>{{ $slot }}</div>
</section>
