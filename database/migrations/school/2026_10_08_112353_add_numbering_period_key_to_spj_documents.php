<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * R6: simpan period_key yang dipakai saat nomor diterbitkan.
     *
     * rebuildSequences() sebelumnya mengelompokkan dokumen memakai
     * reset_period format yang BERLAKU SAAT INI. Bila operator mengubah
     * reset_period (mis. MONTH -> YEAR) setelah nomor terbit, rebuild
     * menempatkan dokumen ke grup periode yang salah sehingga last_number
     * dihitung dari gabungan yang keliru dan nomor berikutnya dapat
     * berulang. Dengan period_key tersimpan per dokumen, rebuild selalu
     * memakai periode saat penerbitan (fallback ke perhitungan current
     * untuk baris lama yang belum punya nilai).
     */
    public function up(): void
    {
        Schema::connection('school')->table('spj_documents', function (Blueprint $table): void {
            $table->string('numbering_period_key', 40)->nullable()->after('sequence_number');
        });
    }

    public function down(): void
    {
        Schema::connection('school')->table('spj_documents', function (Blueprint $table): void {
            $table->dropColumn('numbering_period_key');
        });
    }
};
