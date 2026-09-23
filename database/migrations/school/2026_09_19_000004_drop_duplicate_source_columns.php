<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DROP fisik kolom ganda overlay.
 *
 * Fakta sumber kini hanya dibaca dari tabel mirror ARKAS tetap
 * (arkas_mirror_*) lewat ArkasMirrorResolver; kolom lokal yang
 * menduplikasinya dihapus. Trigger snapshot kolom lokal diganti
 * perekaman event PHP di ArkasSynchronizationServiceV2; trigger
 * status (SOURCE_MISSING/RETURNED) dipertahankan.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $transactionColumns = [
        'no_bukti',
        'transaction_date',
        'description',
        'activity_code',
        'activity_name',
        'account_code',
        'account_name',
        'recipient_name',
        'gross_amount',
        'ppn',
        'pph21',
        'pph22',
        'pph23',
        'pph4',
        'sspd',
        'tax_total',
        'net_amount',
    ];

    /** @var array<int, string> */
    private array $itemColumns = [
        'description',
        'quantity',
        'unit',
        'unit_price',
        'amount',
    ];

    public function up(): void
    {
        $db = DB::connection('school');

        if ($db->getDriverName() === 'sqlite') {
            $db->unprepared('DROP TRIGGER IF EXISTS transactions_source_changed_event');
            $db->unprepared('DROP TRIGGER IF EXISTS transaction_items_source_changed_event');
        }

        $dropped = array_merge($this->transactionColumns, $this->itemColumns);

        foreach (['transactions', 'transaction_items'] as $table) {
            $indexes = $db->table('sqlite_master')
                ->where('type', 'index')
                ->where('tbl_name', $table)
                ->get(['name', 'sql']);

            foreach ($indexes as $index) {
                $definition = (string) ($index->sql ?? '');
                foreach ($dropped as $column) {
                    if (preg_match('/["\(\s,]'.preg_quote($column, '/').'["\s,\)]/i', $definition)) {
                        $db->unprepared('DROP INDEX "'.$index->name.'"');
                        break;
                    }
                }
            }
        }

        foreach ($this->transactionColumns as $column) {
            if (Schema::connection('school')->hasColumn('transactions', $column)) {
                Schema::connection('school')->table('transactions', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        foreach ($this->itemColumns as $column) {
            if (Schema::connection('school')->hasColumn('transaction_items', $column)) {
                Schema::connection('school')->table('transaction_items', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::connection('school')->table('transactions', function (Blueprint $table): void {
            $table->string('no_bukti', 40)->nullable();
            $table->date('transaction_date')->nullable();
            $table->text('description')->nullable();
            $table->string('activity_code', 30)->nullable();
            $table->text('activity_name')->nullable();
            $table->string('account_code', 40)->nullable();
            $table->text('account_name')->nullable();
            $table->string('recipient_name')->nullable();
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('ppn', 18, 2)->default(0);
            $table->decimal('pph21', 18, 2)->default(0);
            $table->decimal('pph22', 18, 2)->default(0);
            $table->decimal('pph23', 18, 2)->default(0);
            $table->decimal('pph4', 18, 2)->default(0);
            $table->decimal('sspd', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
        });

        Schema::connection('school')->table('transaction_items', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
        });
    }
};
