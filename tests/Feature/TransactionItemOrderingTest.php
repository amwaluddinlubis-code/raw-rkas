<?php

namespace Tests\Feature;

use Tests\TestCase;

class TransactionItemOrderingTest extends TestCase
{
    public function test_detail_blade_exposes_reorder_buttons_and_source_id(): void
    {
        $blade = file_get_contents(resource_path('views/transactions/partials/detail/items.blade.php'));

        $this->assertStringContainsString("wire:click=\"moveItem({{ \$item->id }}, 'up')\"", $blade);
        $this->assertStringContainsString("wire:click=\"moveItem({{ \$item->id }}, 'down')\"", $blade);
        $this->assertStringContainsString('ID {{ $item->source_item_id', $blade);
        $this->assertStringContainsString('name="refresh"', $blade); $this->assertStringContainsString('min-w-[180px]', $blade);
    }

    public function test_workspace_persists_item_order_and_audits(): void
    {
        $livewire = file_get_contents(app_path('Livewire/TransactionDetailWorkspace.php'));
        $transaction = file_get_contents(app_path('Models/Transaction.php'));

        $this->assertStringContainsString('public function moveItem(', $livewire);
        $this->assertStringContainsString("'TRANSACTION_ITEMS_REORDERED'", $livewire);
        $this->assertStringContainsString('COALESCE(NULLIF(sort_order, 0), id)', $transaction);
        $this->assertFileExists(database_path('migrations/school').'/2026_10_04_000002_add_sort_order_to_transaction_items.php');
    }
}
