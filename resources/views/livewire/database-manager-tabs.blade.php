<div data-livewire-tabs="true" class="ui-tabs">
    <nav class="ui-tabs-list" role="tablist" aria-label="Navigasi database">
        <button type="button" wire:click="selectTab('overview')" data-tab="overview" class="ui-tab {{ $activeTab === 'overview' ? 'ui-tab-active' : '' }}"><span class="ui-tab-icon"><x-ui.icon name="dashboard" size="sm" /></span><span>Ringkasan</span></button>
        <button type="button" wire:click="selectTab('list')" data-tab="list" class="ui-tab {{ $activeTab === 'list' ? 'ui-tab-active' : '' }}"><span class="ui-tab-icon"><x-ui.icon name="school" size="sm" /></span><span>Database Sekolah</span><span class="db-tab-count">{{ $databaseCount }}</span></button>
        <button type="button" wire:click="selectTab('tables')" data-tab="tables" class="ui-tab {{ $activeTab === 'tables' ? 'ui-tab-active' : '' }}"><span class="ui-tab-icon"><x-ui.icon name="database" size="sm" /></span><span>Explorer Tabel</span><span class="db-tab-count">{{ $tableCount }}</span></button>
        <button type="button" wire:click="selectTab('diagnostic')" data-tab="diagnostic" class="ui-tab {{ $activeTab === 'diagnostic' ? 'ui-tab-active' : '' }}"><span class="ui-tab-icon"><x-ui.icon name="audit" size="sm" /></span><span>Diagnostik</span></button>
        <button type="button" wire:click="selectTab('maintenance')" data-tab="maintenance" class="ui-tab {{ $activeTab === 'maintenance' ? 'ui-tab-active' : '' }}"><span class="ui-tab-icon"><x-ui.icon name="settings" size="sm" /></span><span>Maintenance</span></button>
    </nav>
</div>
