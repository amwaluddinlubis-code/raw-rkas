<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Services\ArkasActivityHierarchyResolver;
use App\Services\SpjTemplateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class SpjProgramHierarchyPlaceholderTest extends TestCase
{
    use SeedsArkasMirror;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Schema::connection('school')->create('arkas_bku_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('source_kas_id');
            $table->json('payload');
        });
        Schema::connection('school')->create('arkas_rkas_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('source_rapbs_id');
            $table->json('payload');
        });
        $this->ensureMirrorTables();
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_placeholder_catalog_exposes_complete_program_hierarchy(): void
    {
        $group = SpjTemplateService::placeholderGroups()['Transaksi & pembayaran'];

        foreach ([
            'KODE_PROGRAM',
            'NAMA_PROGRAM',
            'KODE_SUB_PROGRAM',
            'NAMA_SUB_PROGRAM',
            'KODE_KEGIATAN',
            'NAMA_KEGIATAN',
        ] as $placeholder) {
            $this->assertContains($placeholder, $group);
        }
    }

    public function test_resolver_reads_program_and_sub_program_from_synced_rkas_payload(): void
    {
        DB::connection('school')->table('arkas_bku_rows')->insert([
            'fiscal_year_id' => 77,
            'source_kas_id' => 'KAS-001',
            'payload' => json_encode(['ID_RAPBS' => 'RAPBS-001'], JSON_THROW_ON_ERROR),
        ]);
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 77,
            'source_rapbs_id' => 'RAPBS-001',
            'payload' => json_encode([
                'KODE_PROGRAM' => '01.',
                'NAMA_PROGRAM' => 'Pengembangan Kompetensi Lulusan',
                'KODE_SUB_PROGRAM' => '01.03.',
                'NAMA_SUB_PROGRAM' => 'Pembiayaan Kegiatan Pembelajaran dan Ekstrakurikuler',
                'KODE_KEGIATAN' => '01.03.04.',
                'NAMA_KEGIATAN' => 'Pelaksanaan kegiatan pembelajaran',
            ], JSON_THROW_ON_ERROR),
        ]);

        $transaction = new Transaction([
            'fiscal_year_id' => 77,
            'id_kas_umum' => 'KAS-001',
            'activity_code' => '01.03.04.',
        ]);

        $resolved = app(ArkasActivityHierarchyResolver::class)->resolve($transaction);

        $this->assertSame('01.', $resolved['program_code']);
        $this->assertSame('Pengembangan Kompetensi Lulusan', $resolved['program_name']);
        $this->assertSame('01.03.', $resolved['sub_program_code']);
        $this->assertSame('Pembiayaan Kegiatan Pembelajaran dan Ekstrakurikuler', $resolved['sub_program_name']);
    }

    public function test_resolver_derives_codes_but_does_not_invent_names_for_legacy_payloads(): void
    {
        $transaction = new Transaction(['fiscal_year_id' => 77]);
        $transaction->forceFill(['activity_code' => '02.04.06.']);

        $resolved = app(ArkasActivityHierarchyResolver::class)->resolve($transaction);

        $this->assertSame('02.', $resolved['program_code']);
        $this->assertSame('', $resolved['program_name']);
        $this->assertSame('02.04.', $resolved['sub_program_code']);
        $this->assertSame('', $resolved['sub_program_name']);
    }

    public function test_resolver_reads_program_hierarchy_from_fixed_mirror_chain(): void
    {
        $chain = $this->seedMirrorActivityChain([
            'ID_KODE' => '01.03.04.',
            'URAIAN_KODE' => 'Pelaksanaan kegiatan pembelajaran',
        ]);
        $rapbs = DB::connection('school')->table('arkas_mirror_rapbs')
            ->where('source_key', $chain['rapbs'])
            ->first(['payload']);
        $rapbsPayload = json_decode((string) $rapbs->payload, true, 512, JSON_THROW_ON_ERROR);
        $kasKey = $this->seedMirrorKasUmum(null, [
            'ID_RAPBS_PERIODE' => $chain['rapbs_periode'],
            'KATEGORI_BKU' => 'BELANJA',
            'TANGGAL_TRANSAKSI' => '2026-01-15',
        ]);

        $referenceSuffix = uniqid();
        foreach ([
            ['01.', 'Program Pengembangan'],
            ['01.03.', 'Pembelajaran dan Ekstrakurikuler'],
        ] as [$code, $name]) {
            $referenceKey = 'REF-'.$referenceSuffix.'-'.$code;
            DB::table('arkas_mirror_ref_kode')->insert([
                'source_key' => $referenceKey,
                'payload' => json_encode([
                    'id_ref_kode' => $referenceKey,
                    'id_kode' => $code,
                    'uraian_kode' => $name,
                    'tahun' => 2026,
                    'sumber_dana_id' => 1,
                ], JSON_THROW_ON_ERROR),
                'source_hash' => hash('sha256', $code),
                'synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $transaction = new Transaction([
            'fiscal_year_id' => 77,
            'fund_source_id' => 1,
            'id_kas_umum' => $kasKey,
            'activity_code' => $rapbsPayload['KODE_KEGIATAN'] ?? null,
        ]);

        $resolved = app(ArkasActivityHierarchyResolver::class)->resolve($transaction);

        $this->assertSame('01.', $resolved['program_code']);
        $this->assertSame('Program Pengembangan', $resolved['program_name']);
        $this->assertSame('01.03.', $resolved['sub_program_code']);
        $this->assertSame('Pembelajaran dan Ekstrakurikuler', $resolved['sub_program_name']);
    }

    public function test_arkas_bridge_rkas_contract_exports_program_hierarchy(): void
    {
        $source = (string) file_get_contents(base_path('bridge/src/ARKASBridge/Program.cs'));

        $this->assertStringContainsString('SCHEMA|RKAS|3', $source);
        foreach (['KODE_PROGRAM', 'NAMA_PROGRAM', 'KODE_SUB_PROGRAM', 'NAMA_SUB_PROGRAM'] as $field) {
            $this->assertStringContainsString($field, $source);
        }
    }
}
