<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'school';

    /**
     * Hapus FK gantung checked_by → users pada spj_external_checklist_ticks.
     * Tabel users hanya ada di database pusat; FK ini (skema lama sebelum
     * revisi "tanpa FK") membuat SETIAP DELETE pada transactions gagal
     * dengan "no such table: main.users" pada tenant yang terdampak.
     * Idempotent: no-op bila FK sudah tidak ada.
     */
    public function up(): void
    {
        if (! Schema::connection('school')->hasTable('spj_external_checklist_ticks')) {
            return;
        }

        $foreignKeys = DB::connection('school')->select(
            "PRAGMA foreign_key_list('spj_external_checklist_ticks')"
        );
        $hasUsersFk = collect($foreignKeys)->contains(
            fn (object $key): bool => ($key->table ?? null) === 'users'
        );
        if (! $hasUsersFk) {
            return;
        }

        DB::connection('school')->transaction(function (): void {
            DB::connection('school')->statement('PRAGMA foreign_keys = OFF');
            try {
                DB::connection('school')->statement(
                    <<<'SQL'
                        CREATE TABLE spj_external_checklist_ticks_repaired (
                            id integer primary key autoincrement not null,
                            spj_package_id integer not null,
                            item_key varchar(60) not null,
                            is_checked tinyint(1) not null default '0',
                            checked_by integer null,
                            checked_at datetime null,
                            created_at datetime null,
                            updated_at datetime null,
                            foreign key("spj_package_id") references "spj_packages"("id") on delete cascade,
                            unique("spj_package_id", "item_key")
                        )
                        SQL
                );
                DB::connection('school')->statement(
                    'INSERT INTO spj_external_checklist_ticks_repaired
                        (id, spj_package_id, item_key, is_checked, checked_by, checked_at, created_at, updated_at)
                        SELECT id, spj_package_id, item_key, is_checked, checked_by, checked_at, created_at, updated_at
                        FROM spj_external_checklist_ticks'
                );
                DB::connection('school')->statement('DROP TABLE spj_external_checklist_ticks');
                DB::connection('school')->statement(
                    'ALTER TABLE spj_external_checklist_ticks_repaired RENAME TO spj_external_checklist_ticks'
                );
                DB::connection('school')->statement(
                    'CREATE INDEX spj_external_checklist_ticks_spj_package_id_is_checked_index
                        ON spj_external_checklist_ticks (spj_package_id, is_checked)'
                );
            } finally {
                DB::connection('school')->statement('PRAGMA foreign_keys = ON');
            }
        });
    }

    public function down(): void
    {
        // Perbaikan skema satu arah: FK gantung tidak boleh dikembalikan.
    }
};
