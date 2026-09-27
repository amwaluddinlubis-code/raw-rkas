<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('transaction_source_events', function (Blueprint $table): void {
            $table->index(
                ['transaction_id', 'id'],
                'source_events_transaction_latest_idx'
            );
        });

        Schema::connection('school')->table('transaction_source_reconciliations', function (Blueprint $table): void {
            $table->index(
                ['transaction_id', 'source_event_id', 'source_hash'],
                'source_reconciliation_latest_hash_idx'
            );
        });

        Schema::connection('school')->table('sync_runs', function (Blueprint $table): void {
            $table->index(
                ['fiscal_year_id', 'started_at'],
                'sync_runs_fiscal_year_started_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('sync_runs', function (Blueprint $table): void {
            $table->dropIndex('sync_runs_fiscal_year_started_idx');
        });

        Schema::connection('school')->table('transaction_source_reconciliations', function (Blueprint $table): void {
            $table->dropIndex('source_reconciliation_latest_hash_idx');
        });

        Schema::connection('school')->table('transaction_source_events', function (Blueprint $table): void {
            $table->dropIndex('source_events_transaction_latest_idx');
        });
    }
};
