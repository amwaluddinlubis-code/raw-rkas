<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * R1: activity_references & business_partners kini membawa fund_source_id.
     *
     * synchronizeDerived() sebelumnya menulis kedua tabel referensi tanpa
     * fund_source_id, sehingga data dari sumber dana berbeda saling
     * menimpa (kunci tulis hanya [fiscal_year_id, activity_code] dan
     * [name, npwp]). Kolom baru masuk ke kunci tulis dan unique index.
     *
     * Baris lama di-backfill NULL (sumber dana tidak diketahui); sinkronisasi
     * berikutnya mengisi nilai yang benar dari arkas_rkas_items /
     * arkas_bku_rows. SQLite tidak mendukung ALTER COLUMN / DROP UNIQUE,
     * sehingga dipakai pola create-copy-drop-rename seperti migrasi K2.
     */
    public function up(): void
    {
        // View hierarki bergantung pada activity_references; drop dulu agar
        // rename tabel staging tidak ditolak SQLite, lalu buat ulang.
        DB::connection('school')->statement('DROP VIEW IF EXISTS activity_hierarchy_references');
        $this->rebuildActivityReferences(withFundSource: true);
        $this->rebuildBusinessPartners(withFundSource: true);
        $this->recreateHierarchyView();
    }

    public function down(): void
    {
        DB::connection('school')->statement('DROP VIEW IF EXISTS activity_hierarchy_references');
        // Kembalikan ke skema lama: bila ada baris ganda per kunci lama
        // (akibat pemisahan fund_source), pertahankan id terkecil.
        $connection = DB::connection('school');
        foreach (
            $connection->table('activity_references')
                ->select(['fiscal_year_id', 'activity_code'])
                ->selectRaw('MIN(id) as keep_id')
                ->groupBy(['fiscal_year_id', 'activity_code'])
                ->havingRaw('COUNT(*) > 1')
                ->get() as $duplicate
        ) {
            $connection->table('activity_references')
                ->where('fiscal_year_id', $duplicate->fiscal_year_id)
                ->where('activity_code', $duplicate->activity_code)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }
        foreach (
            $connection->table('business_partners')
                ->select(['name', 'npwp'])
                ->selectRaw('MIN(id) as keep_id')
                ->groupBy(['name', 'npwp'])
                ->havingRaw('COUNT(*) > 1')
                ->get() as $duplicate
        ) {
            $connection->table('business_partners')
                ->where('name', $duplicate->name)
                ->where('npwp', $duplicate->npwp)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        $this->rebuildActivityReferences(withFundSource: false);
        $this->rebuildBusinessPartners(withFundSource: false);
        $this->recreateHierarchyView();
    }

    private function recreateHierarchyView(): void
    {
        DB::connection('school')->statement(<<<'SQL'
            CREATE VIEW IF NOT EXISTS activity_hierarchy_references AS
            SELECT
                program.fiscal_year_id,
                program.activity_code AS program_code,
                program.activity_name AS program_name,
                sub_program.activity_code AS sub_program_code,
                sub_program.activity_name AS sub_program_name,
                activity.activity_code AS activity_code,
                activity.activity_name AS activity_name,
                program.source_ref_code AS program_source_ref_code,
                sub_program.source_ref_code AS sub_program_source_ref_code,
                activity.source_ref_code AS activity_source_ref_code
            FROM activity_references AS program
            INNER JOIN activity_references AS sub_program
                ON sub_program.fiscal_year_id = program.fiscal_year_id
                AND program.activity_code = substr(sub_program.activity_code, 1, 3)
            INNER JOIN activity_references AS activity
                ON activity.fiscal_year_id = sub_program.fiscal_year_id
                AND sub_program.activity_code = substr(activity.activity_code, 1, 6)
            WHERE length(program.activity_code) = 3
                AND length(sub_program.activity_code) = 6
                AND length(activity.activity_code) = 9
        SQL);
    }

    private function rebuildActivityReferences(bool $withFundSource): void
    {
        $table = 'activity_references';
        $staging = 'activity_references_staging';
        $connection = DB::connection('school');

        // Bersihkan sisa staging dari run migrasi yang gagal sebelumnya.
        Schema::connection('school')->dropIfExists($staging);

        Schema::connection('school')->create($staging, function (Blueprint $table) use ($withFundSource): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            if ($withFundSource) {
                $table->unsignedInteger('fund_source_id')->nullable();
                $table->foreign('fund_source_id')->references('id')->on('fund_sources')->restrictOnDelete();
            }
            $table->string('source_ref_code', 100)->nullable();
            $table->string('activity_code', 40);
            $table->text('activity_name')->nullable();
            $table->timestamps();
            // Unique index dibuat setelah rename: nama index unik se-database di SQLite.
        });

        $columns = ['id', 'fiscal_year_id', 'source_ref_code', 'activity_code', 'activity_name', 'created_at', 'updated_at'];
        $select = $columns;
        if ($withFundSource) {
            array_splice($columns, 2, 0, ['fund_source_id']);
            $select = ['id', 'fiscal_year_id', DB::raw('NULL as fund_source_id'), 'source_ref_code', 'activity_code', 'activity_name', 'created_at', 'updated_at'];
        }

        $connection->table($staging)->insertUsing($columns, $connection->table($table)->select($select));

        Schema::connection('school')->drop($table);
        Schema::connection('school')->rename($staging, $table);

        Schema::connection('school')->table($table, function (Blueprint $table) use ($withFundSource): void {
            if ($withFundSource) {
                $table->unique(['fiscal_year_id', 'fund_source_id', 'activity_code'], 'activity_references_context_unique');
            } else {
                $table->unique(['fiscal_year_id', 'activity_code'], 'activity_references_fiscal_year_id_activity_code_unique');
            }
        });
    }

    private function rebuildBusinessPartners(bool $withFundSource): void
    {
        $table = 'business_partners';
        $staging = 'business_partners_staging';
        $connection = DB::connection('school');

        // Bersihkan sisa staging dari run migrasi yang gagal sebelumnya.
        Schema::connection('school')->dropIfExists($staging);

        Schema::connection('school')->create($staging, function (Blueprint $table) use ($withFundSource): void {
            $table->id();
            if ($withFundSource) {
                $table->unsignedInteger('fund_source_id')->nullable();
                $table->foreign('fund_source_id')->references('id')->on('fund_sources')->restrictOnDelete();
            }
            $table->string('name');
            $table->string('npwp', 40)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_business_entity')->default(false);
            $table->boolean('is_arkas_synced')->default(true);
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        $columns = ['id', 'name', 'npwp', 'phone', 'address', 'is_business_entity', 'is_arkas_synced', 'payload', 'created_at', 'updated_at'];
        $select = $columns;
        if ($withFundSource) {
            array_splice($columns, 1, 0, ['fund_source_id']);
            $select = ['id', DB::raw('NULL as fund_source_id'), 'name', 'npwp', 'phone', 'address', 'is_business_entity', 'is_arkas_synced', 'payload', 'created_at', 'updated_at'];
        }

        $connection->table($staging)->insertUsing($columns, $connection->table($table)->select($select));

        Schema::connection('school')->drop($table);
        Schema::connection('school')->rename($staging, $table);

        Schema::connection('school')->table($table, function (Blueprint $table) use ($withFundSource): void {
            if ($withFundSource) {
                $table->unique(['fund_source_id', 'name', 'npwp'], 'business_partners_context_unique');
            } else {
                $table->unique(['name', 'npwp'], 'business_partners_name_npwp_unique');
            }
        });
    }
};
