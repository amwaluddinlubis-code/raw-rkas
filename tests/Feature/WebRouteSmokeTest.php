<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

/**
 * Smoke test seluruh route web GET: memastikan tidak ada yang 500
 * dengan konteks aktif + fixture minimal yang valid.
 *
 * Route mutasi destruktif (sync/reset/provision/destroy/download arsip)
 * sengaja tidak disentuh; tercakup suite khusus masing-masing.
 */
class WebRouteSmokeTest extends TestCase
{
    use RefreshDatabase, SeedsArkasMirror;

    private int $transactionId;

    private int $packageId;

    private int $templateId;

    private int $employeeId;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolasi file database sekolah dari data nyata. PENTING: request
        // HTTP mengaktifkan koneksi file via provisioning, sehingga fixture
        // harus di-seed SETELAH provision (bukan :memory:).
        File::deleteDirectory(storage_path('framework/testing/route-smoke'));
        config()->set('spj.databases_root', storage_path('framework/testing/route-smoke/school-dbs'));
        DB::purge('school');
        Storage::fake('local');

        $school = School::query()->create(['npsn' => '99887766', 'school_code' => 'SCH-SMK', 'name' => 'SD Smoke', 'address' => 'Jl. Smoke']);
        app(SchoolDatabaseManager::class)->provision($school);

        $fund = FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $year->id,
            'principal_name' => 'Kepala Smoke',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Smoke',
            'treasurer_nip' => '198202022002022002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create(['role' => 'ADMIN', 'school_id' => $school->id]);

        $this->actingAs($user);
        $this->withoutMiddleware();
        // Tanpa middleware, $errors tidak terbagi otomatis; sediakan manual
        // agar view form tidak 500 dan tidak menutupi bug sungguhan.
        $this->withViewErrors([]);
        session()->put([
            'active_school_id' => $school->id,
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $fund->id,
        ]);

        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fund->id,
            'id_kas_umum' => 'smoke-kas-1',
            'no_bukti' => 'BKU-SMOKE-01',
            'transaction_date' => '2026-04-07',
            'gross_amount' => 1000000,
            'tax_total' => 110000,
            'net_amount' => 890000,
            'spj_category' => 'BARANG',
        ]);
        $this->mirrorItem($transaction, ['amount' => 1000000]);
        $this->transactionId = $transaction->id;

        $this->packageId = SpjPackage::query()->create([
            'transaction_id' => $transaction->id,
            'status' => 'DRAFT',
        ])->id;

        $this->templateId = DocumentTemplate::query()->create([
            'fiscal_year_id' => $year->id,
            'document_type' => 'SPJ_COVER',
            'name' => 'Cover Smoke',
            'format' => 'xlsx',
            'file_path' => 'document-templates/smoke.xlsx',
        ])->id;

        $this->employeeId = Employee::query()->create([
            'source_type' => 'MANUAL',
            'source_key' => 'smoke-emp-1',
            'name' => 'Pegawai Smoke',
            'is_active' => true,
        ])->id;

        $this->studentId = Student::query()->create([
            'source_key' => 'smoke-siswa-1',
            'name' => 'Siswa Smoke',
            'normalized_name' => 'siswa smoke',
            'nisn' => '0011223344',
            'class_name' => 'VI-A',
            'is_active' => true,
        ])->id;
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_public_and_workspace_routes_have_no_server_error(): void
    {
        $this->assertUrisOk([
            '/masuk',
            '/pilih-sekolah',
            '/pilih-tahun',
            '/',
            '/spj',
            '/spj?tab=persiapan',
            '/spj?tab=paket',
            '/spj?tab=laporan',
            '/spj?tab=monitoring',
            '/spj/penomoran',
            '/spj/penomoran/koreksi',
            "/spj/paket/{$this->packageId}/checklist",
            "/spj/paket/{$this->packageId}/pratinjau",
            '/asisten',
        ]);
    }

    public function test_transaction_and_master_routes_have_no_server_error(): void
    {
        $this->assertUrisOk([
            '/transaksi',
            "/transaksi/{$this->transactionId}",
            '/pajak',
            '/pegawai',
            '/pegawai/tambah/baru',
            "/pegawai/{$this->employeeId}",
            "/pegawai/{$this->employeeId}/ubah",
            '/siswa',
            '/siswa/tambah/baru',
            "/siswa/{$this->studentId}",
            "/siswa/{$this->studentId}/ubah",
            '/referensi',
            '/referensi?tab=harga',
            '/data-sinkron',
            '/data-sinkron/bku',
            '/rekonsiliasi',
        ]);
    }

    public function test_report_routes_have_no_server_error(): void
    {
        $this->assertUrisOk([
            '/laporan-periode',
            '/laporan-periode/bulan/bku/cetak?periode_laporan=4',
            '/laporan-periode/bulan/bku/pdf?periode_laporan=4',
            '/laporan-periode/triwulan/bku/cetak?periode_laporan=2',
            '/laporan-periode/semester/bku/cetak?periode_laporan=1',
            '/laporan-periode/tahunan/bku/cetak',
            '/laporan-periode/tahunan/bku/pdf',
            '/spj/laporan/honor/pilih',
            '/spj/laporan/jasa/pilih',
            '/spj/laporan/honor/xlsx',
            '/spj/laporan/jasa/xlsx',
            '/spj/unduh/xlsx',
            '/laporan-audit',
            '/laporan-audit/unduh/pdf',
            '/laporan-audit/unduh/xlsx',
            '/penganggaran-rkas',
            '/penganggaran-rkas/saran',
            '/penganggaran-rkas/saran/unduh/pagu-awal',
            '/penganggaran-rkas/saran/unduh/sisa-pagu',
        ]);
    }

    public function test_settings_routes_have_no_server_error(): void
    {
        $this->assertUrisOk([
            '/pengaturan/arkas',
            '/pengaturan/arkas/importer',
            '/pengaturan/arkas/mirror',
            '/pengaturan/arkas/mirror/status',
            '/pengaturan/backup',
            '/pengaturan/dapodik',
            '/pengaturan/database-aktif',
            '/pengaturan/database-reset',
            '/pengaturan/format-penomoran',
            '/pengaturan/impersonate',
            '/pengaturan/sekolah',
            '/pengaturan/template-dokumen',
            '/pengaturan/template-dokumen/contoh/xlsx',
            '/pengaturan/user',
        ]);
    }

    public function test_by_design_not_found_routes(): void
    {
        // /setup hanya ada sebelum akun pertama dibuat; kop-surat hanya ada
        // bila sekolah sudah mengunggah kop.
        $this->get('/setup')->assertNotFound();
        $this->get('/pengaturan/sekolah/kop-surat')->assertNotFound();
    }

    public function test_first_activation_provisions_database_record_without_hang(): void
    {
        // Sekolah tanpa SchoolDatabase record: activate() harus membuat
        // record sekali lalu berhenti (regresi rekursi provision↔activate).
        $this->get('/pajak')->assertOk();

        $this->assertDatabaseHas('school_databases', ['school_id' => School::query()->firstOrFail()->id]);
    }

    /** @param list<string> $cases */
    private function assertUrisOk(array $cases): void
    {
        // Tanpa leading slash (env Git Bash merusak nilai berawalan '/').
        $only = ltrim((string) getenv('BISECT_URI'), '/');
        if ($only !== '') {
            $cases = array_values(array_filter($cases, static fn (string $uri): bool => ltrim($uri, '/') === $only));
        }
        if (getenv('THROW_URI') !== '') {
            $this->withoutExceptionHandling();
        }
        $failures = [];
        foreach ($cases as $uri) {
            try {
                $status = $this->get($uri)->getStatusCode();
            } catch (\Throwable $exception) {
                $failures[] = "{$uri} EXCEPTION: ".substr($exception->getMessage(), 0, 160);

                continue;
            }
            if ($status >= 400) {
                $failures[] = "{$uri} HTTP {$status}";
            }
        }

        $this->assertSame([], $failures, 'Route bermasalah: '.implode(' | ', $failures));
    }
}
