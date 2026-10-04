<div class="flex flex-wrap gap-2">
    <a href="{{ route('spj.numbering-workflow') }}" class="ui-btn {{ request()->routeIs('spj.numbering-workflow') ? 'ui-btn-primary' : 'ui-btn-secondary' }} !min-h-9 px-3 py-1.5 text-sm">Penomoran per Triwulan</a>
    <a href="{{ route('spj.numbering-correction') }}" class="ui-btn {{ request()->routeIs('spj.numbering-correction') ? 'ui-btn-primary' : 'ui-btn-secondary' }} !min-h-9 px-3 py-1.5 text-sm">Rollback &amp; Koreksi</a>
</div>
