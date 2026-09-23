<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0);
        });

        // Pertahankan urutan lama (alfabetis document_type) sebagai
        // baseline agar perilaku pratinjau tidak berubah sebelum operator
        // mengatur ulang.
        $years = DB::table('document_templates')->select('fiscal_year_id')->distinct()->pluck('fiscal_year_id');
        foreach ($years as $yearId) {
            $ids = DB::table('document_templates')
                ->where('fiscal_year_id', $yearId)
                ->orderBy('document_type')
                ->orderBy('id')
                ->pluck('id');
            foreach ($ids->values()->all() as $position => $id) {
                DB::table('document_templates')->where('id', $id)->update(['sort_order' => $position + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
