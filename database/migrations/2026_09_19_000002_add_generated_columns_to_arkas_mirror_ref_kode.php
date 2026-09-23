<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Kolom generated STORED untuk mirror ref_kode pusat (lihat migrasi school 000002). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arkas_mirror_ref_kode', function (Blueprint $table): void {
            $table->string('sx_id_kode', 64)->nullable()->virtualAs("json_extract(payload, '$.ID_KODE')");
            $table->integer('sx_tahun')->nullable()->virtualAs("CAST(json_extract(payload, '$.TAHUN') AS INTEGER)");
            $table->index('sx_id_kode');
            $table->index('sx_tahun');
        });

        if (DB::table('arkas_mirror_ref_kode')->exists()) {
            DB::table('arkas_mirror_ref_kode')->update(['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('arkas_mirror_ref_kode', function (Blueprint $table): void {
            $table->dropIndex(['sx_id_kode']);
            $table->dropIndex(['sx_tahun']);
            $table->dropColumn(['sx_id_kode', 'sx_tahun']);
        });
    }
};
