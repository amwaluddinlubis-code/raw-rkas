<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\Transaction;
use App\Services\SpjDocumentTypeRegistry;
use App\Services\SpjTemplateService;
use App\UseCases\Spj\CreateSpjDraftUseCase;
use App\UseCases\Spj\UpdateSpjPackageDetailsUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class SpjPersonInChargeTest extends TestCase
{
    use RefreshDatabase, SeedsArkasMirror;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => 1,
            'principal_name' => 'Kepala Sekolah Uji',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Uji',
            'treasurer_nip' => '198202022002022002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session([
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_person_in_charge_saved_from_employee_roster_reaches_placeholders(): void
    {
        $employee = Employee::query()->create([
            'source_type' => 'DAPODIK',
            'source_key' => 'EMP-PJ-001',
            'name' => 'Siti Rahma, S.Pd.',
            'nip' => '199005052015052001',
            'position' => 'Guru Kelas',
            'staff_type' => 'Guru',
            'is_active' => true,
        ]);
        $transaction = $this->maintenanceTransaction();
        $package = $transaction->spjPackage;

        $response = app(UpdateSpjPackageDetailsUseCase::class)->handle((string) $package->id, $this->payload([
            'person_in_charge_name' => $employee->name,
            'person_in_charge_nip' => $employee->nip,
            'person_in_charge_employee_id' => $employee->id,
        ]));
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $transaction->refresh();
        $this->assertSame('Siti Rahma, S.Pd.', $transaction->person_in_charge_name);
        $this->assertSame('199005052015052001', $transaction->person_in_charge_nip);
        $this->assertSame($employee->id, (int) $transaction->person_in_charge_employee_id);

        $school = School::query()->create([
            'npsn' => '10208246',
            'school_code' => 'SCH-PJ',
            'name' => 'SDN Uji PJ',
            'address' => 'Jl. Uji No. 1',
        ]);
        $package->setRelation('transaction', $transaction);
        $values = app(SpjTemplateService::class)->placeholders($package, $school);
        $this->assertSame('Siti Rahma, S.Pd.', $values['NAMA_PENANGGUNG_JAWAB']);
        $this->assertSame('199005052015052001', $values['NIP_PENANGGUNG_JAWAB']);
    }

    public function test_person_in_charge_can_be_cleared_and_placeholders_fall_back(): void
    {
        $transaction = $this->maintenanceTransaction();
        $package = $transaction->spjPackage;
        $useCase = app(UpdateSpjPackageDetailsUseCase::class);

        $useCase->handle((string) $package->id, $this->payload([
            'person_in_charge_name' => 'Guru Penanggung Jawab',
            'person_in_charge_nip' => '198703032010011002',
            'person_in_charge_employee_id' => null,
        ]));
        $this->assertSame('Guru Penanggung Jawab', $transaction->refresh()->person_in_charge_name);

        $useCase->handle((string) $package->id, $this->payload([
            'person_in_charge_name' => '',
            'person_in_charge_nip' => '',
            'person_in_charge_employee_id' => '',
        ]));
        $transaction->refresh();
        // Request mentah membawa string kosong; middleware HTTP mengubahnya
        // menjadi null pada alur nyata. Yang penting nilainya kosong.
        $this->assertTrue(blank($transaction->person_in_charge_name));
        $this->assertTrue(blank($transaction->person_in_charge_nip));
        $this->assertTrue(blank($transaction->person_in_charge_employee_id));

        $school = School::query()->create([
            'npsn' => '10208246',
            'school_code' => 'SCH-PJ2',
            'name' => 'SDN Uji PJ Kosong',
            'address' => 'Jl. Uji No. 2',
        ]);
        $package->setRelation('transaction', $transaction);
        $values = app(SpjTemplateService::class)->placeholders($package, $school);
        $this->assertSame(SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE, $values['NAMA_PENANGGUNG_JAWAB']);
        $this->assertSame(SpjDocumentTypeRegistry::EMPTY_SCALAR_VALUE, $values['NIP_PENANGGUNG_JAWAB']);
    }

    private function maintenanceTransaction(): Transaction
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU-PJ-001',
            'transaction_date' => '2026-03-10',
            'spj_category' => 'PEMELIHARAAN',
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'tax_total' => 0,
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);
        $this->mirrorItem($transaction, [
            'description' => 'Upah pemeliharaan uji',
            'item_description' => 'Upah pemeliharaan uji',
            'quantity' => 1,
            'unit' => 'paket',
            'unit_price' => 100000,
            'amount' => 100000,
        ]);
        app(CreateSpjDraftUseCase::class)->handle((string) $transaction->id);

        return $transaction->fresh();
    }

    /** @param array<string, mixed> $personInCharge */
    private function payload(array $personInCharge): Request
    {
        return Request::create('/spj/paket', 'PUT', array_merge([
            'spj_category' => 'PEMELIHARAAN',
            'payment_description' => 'Pemeliharaan uji PJ',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Penerima Uji',
            'work_description' => 'Perbaikan atap uji',
            'work_location' => 'SDN Uji',
            'work_started_at' => '2026-03-02',
            'work_completed_at' => '2026-03-07',
        ], $personInCharge));
    }
}
