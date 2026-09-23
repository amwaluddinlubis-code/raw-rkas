@props([
    'columns' => [],
    'data' => null,
    'mobileCard' => null,
    'emptyMessage' => 'Belum ada data tersedia.',
    'emptyAction' => null,
])
@php($rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.'))
<section class="overflow-hidden rounded-2xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] shadow">
    {{-- Mobile Cards --}}
    <div class="grid gap-3 p-4 lg:hidden">
        @forelse($data as $item)
            @if($mobileCard)
                {{ $mobileCard($item) }}
            @else
                <article class="rounded-xl border border-[var(--ui-line)] bg-[var(--ui-surface-base)] p-4 shadow hover:shadow-md transition">
                    @foreach($columns as $column)
                        <div class="mb-2 last:mb-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">{{ $column['label'] }}</p>
                            <p class="mt-1 text-base {{ $column['class'] ?? 'text-[var(--ui-fg)]' }}">
                                @if(isset($column['format']) && $column['format'] === 'currency')
                                    {{ $rupiah($column['value']($item)) }}
                                @elseif(isset($column['format']) && $column['format'] === 'boolean')
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $column['value']($item) ? 'bg-emerald-50 text-emerald-700' : 'bg-[var(--ui-surface-muted)] text-[var(--ui-fg-muted)]' }}">
                                        {{ $column['value']($item) ? 'Ya' : 'Tidak' }}
                                    </span>
                                @else
                                    {{ $column['value']($item) }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </article>
            @endif
        @empty
            <div class="rounded-xl border border-dashed p-8 text-center">
                <p class="font-semibold text-[var(--ui-fg-strong)]">{{ $emptyMessage }}</p>
                @if($emptyAction)
                    <p class="mt-1 text-base text-[var(--ui-fg-muted)]">{{ $emptyAction }}</p>
                @endif
            </div>
        @endforelse
    </div>

    {{-- Desktop Table --}}
    <div class="hidden lg:block overflow-x-auto">
        <table data-pagination="auto" class="min-w-full divide-y divide-[var(--ui-line)] text-base">
            <thead class="bg-[var(--ui-surface-soft)]">
                <tr>
                    @foreach($columns as $column)
                        <th class="px-4 py-3 {{ $column['headerClass'] ?? 'text-left' }} text-xs font-bold uppercase tracking-wide text-[var(--ui-fg-muted)]">
                            {{ $column['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ui-line)] bg-[var(--ui-surface-base)]">
                @forelse($data as $item)
                    <tr class="transition hover:bg-[var(--ui-table-row-hover)]">
                        @foreach($columns as $column)
                            <td class="px-4 py-4 {{ $column['class'] ?? '' }}">
                                @if(isset($column['format']) && $column['format'] === 'currency')
                                    <span class="whitespace-nowrap font-semibold text-[var(--ui-fg)]">{{ $rupiah($column['value']($item)) }}</span>
                                @elseif(isset($column['format']) && $column['format'] === 'boolean')
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $column['value']($item) ? 'bg-emerald-50 text-emerald-700' : 'bg-[var(--ui-surface-muted)] text-[var(--ui-fg-muted)]' }}">
                                        {{ $column['value']($item) ? 'Ya' : 'Tidak' }}
                                    </span>
                                @elseif(isset($column['action']))
                                    {{ $column['action']($item) }}
                                @else
                                    {{ $column['value']($item) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="px-5 py-14 text-center">
                            <p class="font-semibold text-[var(--ui-fg-strong)]">{{ $emptyMessage }}</p>
                            @if($emptyAction)
                                <p class="mt-1 text-base text-[var(--ui-fg-muted)]">{{ $emptyAction }}</p>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
