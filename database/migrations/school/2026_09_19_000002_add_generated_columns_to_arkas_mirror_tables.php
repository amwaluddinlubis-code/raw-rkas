<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom generated (STORED) di atas payload JSON mirror.
 * Bukan duplikasi: nilai selalu diturunkan dari payload (single source),
 * ada agar filter/sort/pagination SQL tetap berindeks tanpa kolom cache
 * manual di tabel overlay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('arkas_mirror_kas_umum', function (Blueprint $table): void {
            $table->string('sx_id_kas_umum', 64)->nullable()->virtualAs("json_extract(payload, '$.ID_KAS_UMUM')");
            $table->string('sx_no_bukti', 64)->nullable()->virtualAs("json_extract(payload, '$.NO_BUKTI')");
            $table->string('sx_tanggal', 32)->nullable()->virtualAs("json_extract(payload, '$.TANGGAL_TRANSAKSI')");
            $table->string('sx_kategori', 32)->nullable()->virtualAs("json_extract(payload, '$.KATEGORI_BKU')");
            $table->decimal('sx_jumlah', 18, 2)->nullable()->virtualAs("json_extract(payload, '$.JUMLAH')");
            $table->string('sx_rapbs_periode', 64)->nullable()->virtualAs("json_extract(payload, '$.ID_RAPBS_PERIODE')");
            $table->string('sx_parent', 64)->nullable()->virtualAs("json_extract(payload, '$.PARENT_ID_KAS_UMUM')");
            $table->index('sx_no_bukti');
            $table->index('sx_tanggal');
            $table->index('sx_kategori');
            $table->index('sx_id_kas_umum');
        });

        Schema::connection('school')->table('arkas_mirror_rapbs', function (Blueprint $table): void {
            $table->string('sx_id_rapbs', 64)->nullable()->virtualAs("json_extract(payload, '$.ID_RAPBS')");
            $table->string('sx_kode_rekening', 40)->nullable()->virtualAs("json_extract(payload, '$.KODE_REKENING')");
            $table->index('sx_id_rapbs');
        });

        Schema::connection('school')->table('arkas_mirror_rapbs_periode', function (Blueprint $table): void {
            $table->string('sx_id_rapbs_periode', 64)->nullable()->virtualAs("json_extract(payload, '$.ID_RAPBS_PERIODE')");
            $table->string('sx_id_rapbs', 64)->nullable()->virtualAs("json_extract(payload, '$.ID_RAPBS')");
            $table->string('sx_id_periode', 32)->nullable()->virtualAs("json_extract(payload, '$.ID_PERIODE')");
            $table->index('sx_id_rapbs_periode');
            $table->index('sx_id_rapbs');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('arkas_mirror_kas_umum', function (Blueprint $table): void {
            $table->dropIndex(['sx_no_bukti']);
            $table->dropIndex(['sx_tanggal']);
            $table->dropIndex(['sx_kategori']);
            $table->dropIndex(['sx_id_kas_umum']);
            $table->dropColumn(['sx_id_kas_umum', 'sx_no_bukti', 'sx_tanggal', 'sx_kategori', 'sx_jumlah', 'sx_rapbs_periode', 'sx_parent']);
        });

        Schema::connection('school')->table('arkas_mirror_rapbs', function (Blueprint $table): void {
            $table->dropIndex(['sx_id_rapbs']);
            $table->dropColumn(['sx_id_rapbs', 'sx_kode_rekening']);
        });

        Schema::connection('school')->table('arkas_mirror_rapbs_periode', function (Blueprint $table): void {
            $table->dropIndex(['sx_id_rapbs_periode']);
            $table->dropIndex(['sx_id_rapbs']);
            $table->dropColumn(['sx_id_rapbs_periode', 'sx_id_rapbs', 'sx_id_periode']);
        });
    }

    private function backfill(?string $connection, string $table): void
    {
        // STORED GENERATED columns dihitung SQLite saat tulis; touch ulang
        // agar baris lama mendapat nilai generated.
        $db = DB::connection($connection);
        if (! $db->table($table)->exists()) {
            return;
        }

        $db->table($table)->update(['updated_at' => now()]);
    }
};
