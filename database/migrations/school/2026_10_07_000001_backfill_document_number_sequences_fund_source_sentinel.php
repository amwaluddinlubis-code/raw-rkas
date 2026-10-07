<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sentinel fund_source_id untuk sequence tanpa sumber dana (NULL legacy).
     *
     * SQLite menganggap NULL sebagai nilai distinct di dalam unique index,
     * sehingga dua baris sequence (fiscal_year_id, NULL, format_name,
     * period_key) dapat terbentuk bersamaan saat race; lockForUpdate lalu
     * membaca salah satunya secara sembarang dan dua dokumen mendapat nomor
     * identik. Sentinel 0 membuat unique index benar-benar melindungi grup
     * tanpa sumber dana. Nilai 0 aman karena id fund_sources selalu positif.
     */
    private const NULL_FUND_SOURCE_SENTINEL = 0;

    public function up(): void
    {
        $connection = DB::connection('school');

        // 1. Gabungkan baris duplikat per grup canonical yang terbentuk
        //    sebelum sentinel; pertahankan last_number tertinggi agar
        //    penomoran tidak pernah mengulang nomor yang sudah terbit.
        $duplicates = $connection->table('document_number_sequences')
            ->select(['fiscal_year_id', 'format_name', 'period_key'])
            ->selectRaw('COALESCE(fund_source_id, ?) as fund_source_key', [self::NULL_FUND_SOURCE_SENTINEL])
            ->groupBy(['fiscal_year_id', 'format_name', 'period_key', 'fund_source_key'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = $connection->table('document_number_sequences')
                ->where('fiscal_year_id', $duplicate->fiscal_year_id)
                ->where('format_name', $duplicate->format_name)
                ->where('period_key', $duplicate->period_key)
                ->whereRaw('COALESCE(fund_source_id, ?) = ?', [self::NULL_FUND_SOURCE_SENTINEL, $duplicate->fund_source_key])
                ->orderByDesc('last_number')
                ->orderBy('id')
                ->get();
            $rows->shift();
            $connection->table('document_number_sequences')->whereIn('id', $rows->pluck('id'))->delete();
        }

        // 2. Backfill NULL ke sentinel.
        $connection->table('document_number_sequences')
            ->whereNull('fund_source_id')
            ->update(['fund_source_id' => self::NULL_FUND_SOURCE_SENTINEL]);

        // 3. Bangun ulang tabel agar kolom NOT NULL; SQLite tidak
        //    mendukung ALTER COLUMN sehingga dipakai pola create-copy-drop-rename.
        $this->rebuildTable(notNull: true);
    }

    public function down(): void
    {
        $this->rebuildTable(notNull: false);

        DB::connection('school')->table('document_number_sequences')
            ->where('fund_source_id', self::NULL_FUND_SOURCE_SENTINEL)
            ->update(['fund_source_id' => null]);
    }

    private function rebuildTable(bool $notNull): void
    {
        $table = 'document_number_sequences';
        $staging = 'document_number_sequences_staging';
        $connection = DB::connection('school');

        Schema::connection('school')->create($staging, function (Blueprint $table) use ($notNull): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $fundSourceColumn = $table->unsignedInteger('fund_source_id')->nullable(! $notNull);
            if ($notNull) {
                $fundSourceColumn->default(self::NULL_FUND_SOURCE_SENTINEL);
            }
            $table->string('format_name', 40);
            $table->string('period_key', 40);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(
                ['fiscal_year_id', 'fund_source_id', 'format_name', 'period_key'],
                'document_number_sequences_context_unique',
            );
            $table->index(['fiscal_year_id', 'fund_source_id'], 'document_number_sequences_context_index');
        });

        $connection->table($staging)->insertUsing(
            ['id', 'fiscal_year_id', 'fund_source_id', 'format_name', 'period_key', 'last_number', 'created_at', 'updated_at'],
            $connection->table($table)->select([
                'id',
                'fiscal_year_id',
                DB::raw('COALESCE(fund_source_id, '.self::NULL_FUND_SOURCE_SENTINEL.')'),
                'format_name',
                'period_key',
                'last_number',
                'created_at',
                'updated_at',
            ]),
        );

        Schema::connection('school')->drop($table);
        Schema::connection('school')->rename($staging, $table);
    }
};
