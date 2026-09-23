<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Lookup ID_REF_KODE pada mirror ref_kode pusat untuk enrich activity. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arkas_mirror_ref_kode', function (Blueprint $table): void {
            $table->dropIndex(['sx_id_kode']);
            $table->dropIndex(['sx_tahun']);
            $table->dropColumn(['sx_id_kode', 'sx_tahun']);
        });
        Schema::table('arkas_mirror_ref_kode', function (Blueprint $table): void {
            $table->string('sx_id_kode', 64)->nullable()->virtualAs("json_extract(payload, '$.id_kode')");
            $table->integer('sx_tahun')->nullable()->virtualAs("CAST(json_extract(payload, '$.tahun') AS INTEGER)");
            $table->index('sx_id_kode');
            $table->index('sx_tahun');
        });
        Schema::table('arkas_mirror_ref_kode', function (Blueprint $table): void {
            $table->string('sx_id_ref_kode', 64)->nullable()->virtualAs("json_extract(payload, '$.id_ref_kode')");
            $table->index('sx_id_ref_kode');
        });

        if (DB::table('arkas_mirror_ref_kode')->exists()) {
            DB::table('arkas_mirror_ref_kode')->update(['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('arkas_mirror_ref_kode', function (Blueprint $table): void {
            $table->dropIndex(['sx_id_ref_kode']);
            $table->dropColumn('sx_id_ref_kode');
        });
    }
};
