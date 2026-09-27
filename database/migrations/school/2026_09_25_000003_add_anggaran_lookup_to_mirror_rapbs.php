<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom lookup anggaran pada rapbs mirror agar snapshot() dapat memfilter
 * ID_ANGGARAN di database (bukan memuat seluruh revisi lalu saring di PHP).
 * Payload rapbs berkunci lowercase; coalesce menjaga varian uppercase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('arkas_mirror_rapbs', function (Blueprint $table): void {
            $table->string('sx_id_anggaran', 64)->nullable()->virtualAs("coalesce(json_extract(payload, '$.id_anggaran'), json_extract(payload, '$.ID_ANGGARAN'))");
            $table->index('sx_id_anggaran');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('arkas_mirror_rapbs', function (Blueprint $table): void {
            $table->dropIndex(['sx_id_anggaran']);
            $table->dropColumn(['sx_id_anggaran']);
        });
    }
};
