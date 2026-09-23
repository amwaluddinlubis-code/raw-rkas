<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payload mirror rapbs/rapbs_periode/ref_kode berkunci lowercase (perintah
 * `rows` mentah); ekspresi generated 000002 memakai uppercase sehingga
 * NULL. Bangun ulang dengan lowercase. kas_umum (enriched uppercase) benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('arkas_mirror_rapbs', function (Blueprint $table): void {
            $table->dropIndex(['sx_id_rapbs']);
            $table->dropColumn(['sx_id_rapbs', 'sx_kode_rekening']);
        });
        Schema::connection('school')->table('arkas_mirror_rapbs', function (Blueprint $table): void {
            $table->string('sx_id_rapbs', 64)->nullable()->virtualAs("json_extract(payload, '$.id_rapbs')");
            $table->string('sx_kode_rekening', 40)->nullable()->virtualAs("json_extract(payload, '$.kode_rekening')");
            $table->index('sx_id_rapbs');
        });

        Schema::connection('school')->table('arkas_mirror_rapbs_periode', function (Blueprint $table): void {
            $table->dropIndex(['sx_id_rapbs_periode']);
            $table->dropIndex(['sx_id_rapbs']);
            $table->dropColumn(['sx_id_rapbs_periode', 'sx_id_rapbs', 'sx_id_periode']);
        });
        Schema::connection('school')->table('arkas_mirror_rapbs_periode', function (Blueprint $table): void {
            $table->string('sx_id_rapbs_periode', 64)->nullable()->virtualAs("json_extract(payload, '$.id_rapbs_periode')");
            $table->string('sx_id_rapbs', 64)->nullable()->virtualAs("json_extract(payload, '$.id_rapbs')");
            $table->string('sx_id_periode', 32)->nullable()->virtualAs("json_extract(payload, '$.id_periode')");
            $table->index('sx_id_rapbs_periode');
            $table->index('sx_id_rapbs');
        });
    }

    public function down(): void
    {
        // Tidak dikembalikan ke uppercase yang rusak; cukup hapus.
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
};
