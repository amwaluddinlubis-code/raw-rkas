<div data-livewire-tabs="true" class="db-tabbar">
    <nav class="db-tab-list" aria-label="Navigasi database">
        <button type="button" wire:click="selectTab('overview')" data-tab="overview" class="tab-btn" data-active="{{ $activeTab === 'overview' ? 'true' : 'false' }}"><span class="ui-tab-icon"><x-ui.icon name="dashboard" size="sm" /></span><span>Ringkasan</span></button>
        <button type="button" wire:click="selectTab('list')" data-tab="list" class="tab-btn" data-active="{{ $activeTab === 'list' ? 'true' : 'false' }}"><span class="ui-tab-icon"><x-ui.icon name="school" size="sm" /></span><span>Database Sekolah</span><span class="db-tab-count">{{ $databaseCount }}</span></button>
        <button type="button" wire:click="selectTab('tables')" data-tab="tables" class="tab-btn" data-active="{{ $activeTab === 'tables' ? 'true' : 'false' }}"><span class="ui-tab-icon"><x-ui.icon name="database" size="sm" /></span><span>Explorer Tabel</span><span class="db-tab-count">{{ $tableCount }}</span></button>
        <button type="button" wire:click="selectTab('diagnostic')" data-tab="diagnostic" class="tab-btn" data-active="{{ $activeTab === 'diagnostic' ? 'true' : 'false' }}"><span class="ui-tab-icon"><x-ui.icon name="audit" size="sm" /></span><span>Diagnostik</span></button>
        <button type="button" wire:click="selectTab('maintenance')" data-tab="maintenance" class="tab-btn" data-active="{{ $activeTab === 'maintenance' ? 'true' : 'false' }}"><span class="ui-tab-icon"><x-ui.icon name="settings" size="sm" /></span><span>Maintenance</span></button>
    </nav>
</div>
