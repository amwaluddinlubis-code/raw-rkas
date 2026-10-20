@if (! empty($widths))
    <colgroup>
        @foreach ($widths as $width)
            <col style="width: {{ $width }}">
        @endforeach
    </colgroup>
@endif
