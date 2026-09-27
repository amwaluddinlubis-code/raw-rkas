<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom lookup daftar acuan harga (satuan, harga, batas atas + kolom
 * 150449 yang belum terpasang karena tabel dibuat runtime setelah migrasi
 * tersebut tercatat). ReferenceLookupService sudah memakai kolom sx_*
 * bila ada (hasColumn), sehingga migrasi ini murni mempercepat tanpa
 * mengubah perilaku baca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arkas_mirror_ref_acuan_barang')) {
            return;
        }

        Schema::table('arkas_mirror_ref_acuan_barang', function (Blueprint $table): void {
            $columns = [
                'sx_id_barang' => "coalesce(json_extract(payload, '$.id_barang'), json_extract(payload, '$.ID_BARANG'))",
                'sx_kode_rekening' => "coalesce(json_extract(payload, '$.kode_rekening'), json_extract(payload, '$.KODE_REKENING'))",
                'sx_nama_barang' => "coalesce(json_extract(payload, '$.nama_barang'), json_extract(payload, '$.NAMA_BARANG'))",
                'sx_satuan' => "coalesce(json_extract(payload, '$.satuan'), json_extract(payload, '$.SATUAN'))",
            ];
            foreach ($columns as $name => $expression) {
                if (! Schema::hasColumn('arkas_mirror_ref_acuan_barang', $name)) {
                    $table->string($name, $name === 'sx_nama_barang' ? 255 : 64)->nullable()->virtualAs($expression);
                }
            }
            if (! Schema::hasColumn('arkas_mirror_ref_acuan_barang', 'sx_tahun')) {
                $table->integer('sx_tahun')->nullable()->virtualAs("CAST(coalesce(json_extract(payload, '$.tahun'), json_extract(payload, '$.TAHUN')) AS INTEGER)");
            }
            foreach (['sx_harga_barang', 'sx_batas_atas'] as $name) {
                if (! Schema::hasColumn('arkas_mirror_ref_acuan_barang', $name)) {
                    $key = $name === 'sx_harga_barang' ? 'harga_barang' : 'batas_atas';
                    $table->decimal($name, 18, 2)->nullable()->virtualAs("CAST(coalesce(json_extract(payload, '$.{$key}'), json_extract(payload, '$.".strtoupper($key)."')) AS REAL)");
                }
            }
        });

        Schema::table('arkas_mirror_ref_acuan_barang', function (Blueprint $table): void {
            $table->index(['sx_tahun', 'sx_nama_barang', 'sx_satuan'], 'ref_acuan_tahun_nama_satuan_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('arkas_mirror_ref_acuan_barang')) {
            return;
        }

        Schema::table('arkas_mirror_ref_acuan_barang', function (Blueprint $table): void {
            $table->dropIndex('ref_acuan_tahun_nama_satuan_idx');
            $table->dropColumn(['sx_satuan', 'sx_harga_barang', 'sx_batas_atas']);
        });
    }
};
