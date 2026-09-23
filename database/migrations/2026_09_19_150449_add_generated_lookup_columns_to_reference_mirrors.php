<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('arkas_mirror_ref_rekening')) {
            Schema::table('arkas_mirror_ref_rekening', function (Blueprint $table): void {
                $table->string('sx_kode_rekening', 64)->nullable()->virtualAs("coalesce(json_extract(payload, '$.kode_rekening'), json_extract(payload, '$.KODE_REKENING'))");
                $table->string('sx_rekening', 255)->nullable()->virtualAs("coalesce(json_extract(payload, '$.rekening'), json_extract(payload, '$.NAMA_REKENING'))");
                $table->integer('sx_tahun')->nullable()->virtualAs("CAST(coalesce(json_extract(payload, '$.tahun'), json_extract(payload, '$.TAHUN')) AS INTEGER)");
                $table->index('sx_kode_rekening');
                $table->index('sx_tahun');
            });
        }

        if (Schema::hasTable('arkas_mirror_ref_acuan_barang')) {
            Schema::table('arkas_mirror_ref_acuan_barang', function (Blueprint $table): void {
                $table->string('sx_id_barang', 64)->nullable()->virtualAs("coalesce(json_extract(payload, '$.id_barang'), json_extract(payload, '$.ID_BARANG'))");
                $table->string('sx_kode_rekening', 64)->nullable()->virtualAs("coalesce(json_extract(payload, '$.kode_rekening'), json_extract(payload, '$.KODE_REKENING'))");
                $table->string('sx_nama_barang', 255)->nullable()->virtualAs("coalesce(json_extract(payload, '$.nama_barang'), json_extract(payload, '$.NAMA_BARANG'))");
                $table->integer('sx_tahun')->nullable()->virtualAs("CAST(coalesce(json_extract(payload, '$.tahun'), json_extract(payload, '$.TAHUN')) AS INTEGER)");
                $table->index('sx_id_barang');
                $table->index('sx_kode_rekening');
                $table->index('sx_tahun');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('arkas_mirror_ref_rekening')) {
            Schema::table('arkas_mirror_ref_rekening', function (Blueprint $table): void {
                $table->dropIndex(['sx_kode_rekening']);
                $table->dropIndex(['sx_tahun']);
                $table->dropColumn(['sx_kode_rekening', 'sx_rekening', 'sx_tahun']);
            });
        }

        if (Schema::hasTable('arkas_mirror_ref_acuan_barang')) {
            Schema::table('arkas_mirror_ref_acuan_barang', function (Blueprint $table): void {
                $table->dropIndex(['sx_id_barang']);
                $table->dropIndex(['sx_kode_rekening']);
                $table->dropIndex(['sx_tahun']);
                $table->dropColumn(['sx_id_barang', 'sx_kode_rekening', 'sx_nama_barang', 'sx_tahun']);
            });
        }
    }
};
