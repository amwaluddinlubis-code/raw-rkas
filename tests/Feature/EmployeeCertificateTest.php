<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeCertificateTest extends TestCase
{
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
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    private function operator(): User
    {
        return User::factory()->create(['role' => User::ROLE_OPERATOR]);
    }

    private function employee(): Employee
    {
        return Employee::query()->create([
            'source_type' => 'MANUAL',
            'source_key' => 'MANUAL:test-1',
            'name' => 'Guru Uji',
            'normalized_name' => 'guru uji',
            'is_active' => true,
        ]);
    }

    public function test_operator_can_add_and_update_sk_with_audit(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->operator())
            ->withoutMiddleware()
            ->post(route('employees.certificates.store', $employee), [
                'kind' => EmployeeCertificate::KIND_SK_GTT_PTT,
                'number' => '800/123/2026',
                'issued_date' => '2026-01-05',
                'valid_until' => '2026-12-31',
            ])
            ->assertRedirect(route('employees.show', $employee));

        $certificate = EmployeeCertificate::query()->first();
        $this->assertNotNull($certificate);
        $this->assertSame('800/123/2026', $certificate->number);
        $this->assertTrue(
            DB::connection('school')->table('operational_audit_logs')
                ->where('entity_type', 'EMPLOYEE_SK')->where('action', 'TAMBAH')->exists()
        );

        $this->actingAs($this->operator())
            ->withoutMiddleware()
            ->put(route('employees.certificates.update', $certificate), [
                'kind' => EmployeeCertificate::KIND_SK_GTT_PTT,
                'number' => '800/999/2026',
            ])
            ->assertRedirect(route('employees.show', $employee));

        $this->assertSame('800/999/2026', $certificate->fresh()->number);
    }

    public function test_expired_and_expiring_status_are_detected(): void
    {
        $expired = new EmployeeCertificate(['valid_until' => now()->subDay()->toDateString()]);
        $soon = new EmployeeCertificate(['valid_until' => now()->addDays(10)->toDateString()]);
        $later = new EmployeeCertificate(['valid_until' => now()->addDays(90)->toDateString()]);
        $indefinite = new EmployeeCertificate(['valid_until' => null]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($soon->isExpired());
        $this->assertTrue($soon->isExpiringSoon());
        $this->assertFalse($later->isExpiringSoon());
        $this->assertFalse($indefinite->isExpired());
        $this->assertFalse($indefinite->isExpiringSoon());
    }

    public function test_sk_file_can_be_uploaded_downloaded_and_deleted(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->operator())
            ->withoutMiddleware()
            ->post(route('employees.certificates.store', $employee), [
                'kind' => EmployeeCertificate::KIND_SK_GTT_PTT,
                'number' => '800/123/2026',
                'file' => UploadedFile::fake()->create('sk.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('employees.show', $employee));

        $certificate = EmployeeCertificate::query()->first();
        $this->assertNotNull($certificate->file_path);
        $this->assertFileExists($certificate->file_path);
        $this->assertStringContainsString('SK', $certificate->file_path);

        $this->actingAs($this->operator())
            ->withoutMiddleware()
            ->get(route('employees.certificates.download', $certificate))
            ->assertOk();

        $storedPath = $certificate->file_path;
        $this->actingAs($this->operator())
            ->withoutMiddleware()
            ->delete(route('employees.certificates.destroy', $certificate))
            ->assertRedirect(route('employees.show', $employee));

        $this->assertFileDoesNotExist($storedPath);
    }

    public function test_employee_mutations_are_audited(): void
    {
        $this->actingAs($this->operator())
            ->withoutMiddleware()
            ->post(route('employees.store'), ['name' => 'Pegawai Audit', 'is_active' => true])
            ->assertRedirect();

        $employee = Employee::query()->where('name', 'Pegawai Audit')->first();
        $this->assertNotNull($employee);
        $this->assertTrue(
            DB::connection('school')->table('operational_audit_logs')
                ->where('entity_type', 'EMPLOYEE')->where('action', 'TAMBAH')->exists()
        );

        $this->actingAs($this->operator())
            ->withoutMiddleware()
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect(route('employees.index'));

        $this->assertTrue(
            DB::connection('school')->table('operational_audit_logs')
                ->where('entity_type', 'EMPLOYEE')->where('action', 'HAPUS')->exists()
        );
    }
}
