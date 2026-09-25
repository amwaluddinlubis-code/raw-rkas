<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Models\User;
use App\Services\DocumentStoragePathService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use RuntimeException;
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
        File::deleteDirectory(storage_path('app/generated-documents/SK'));
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

    public function test_sk_upload_rejects_disguised_pdf_mime_type(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->operator())
            ->withoutMiddleware()
            ->post(route('employees.certificates.store', $employee), [
                'kind' => EmployeeCertificate::KIND_SK_GTT_PTT,
                'number' => '800/123/2026',
                'file' => UploadedFile::fake()->create('sk.pdf', 100, 'text/plain'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, EmployeeCertificate::query()->count());
    }

    public function test_successful_sk_file_replacement_removes_previous_file(): void
    {
        $employee = $this->employee();
        $operator = $this->operator();

        $this->actingAs($operator)
            ->withoutMiddleware()
            ->post(route('employees.certificates.store', $employee), [
                'kind' => EmployeeCertificate::KIND_SK_GTT_PTT,
                'number' => '800/123/2026',
                'file' => UploadedFile::fake()->create('sk-lama.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('employees.show', $employee));

        $certificate = EmployeeCertificate::query()->firstOrFail();
        $previousPath = $certificate->file_path;
        $this->assertFileExists($previousPath);

        $this->actingAs($operator)
            ->withoutMiddleware()
            ->put(route('employees.certificates.update', $certificate), [
                'kind' => EmployeeCertificate::KIND_SK_GTT_PTT,
                'number' => '800/124/2026',
                'file' => UploadedFile::fake()->create('sk-baru.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('employees.show', $employee));

        $certificate->refresh();
        $this->assertSame('800/124/2026', $certificate->number);
        $this->assertSame('sk-baru.pdf', $certificate->file_name);
        $this->assertNotSame($previousPath, $certificate->file_path);
        $this->assertFileDoesNotExist($previousPath);
        $this->assertFileExists($certificate->file_path);
    }

    public function test_failed_sk_file_replacement_preserves_previous_file_and_metadata(): void
    {
        $employee = $this->employee();
        $operator = $this->operator();

        $this->actingAs($operator)
            ->withoutMiddleware()
            ->post(route('employees.certificates.store', $employee), [
                'kind' => EmployeeCertificate::KIND_SK_GTT_PTT,
                'number' => '800/123/2026',
                'file' => UploadedFile::fake()->create('sk-lama.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('employees.show', $employee));

        $certificate = EmployeeCertificate::query()->firstOrFail();
        $previousPath = $certificate->file_path;
        $this->assertFileExists($previousPath);

        $this->partialMock(DocumentStoragePathService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('persistEmployeeCertificate')
                ->once()
                ->andThrow(new RuntimeException('simulated storage failure'));
        });

        $this->actingAs($operator)
            ->withoutMiddleware()
            ->put(route('employees.certificates.update', $certificate), [
                'kind' => EmployeeCertificate::KIND_SK_GTT_PTT,
                'number' => '800/999/2026',
                'file' => UploadedFile::fake()->create('sk-baru.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(500);

        $certificate->refresh();
        $this->assertSame('800/123/2026', $certificate->number);
        $this->assertSame($previousPath, $certificate->file_path);
        $this->assertFileExists($previousPath);
    }

    public function test_sk_routes_preserve_mutation_and_read_boundaries(): void
    {
        $routes = app('router')->getRoutes();

        foreach ([
            'employees.certificates.store',
            'employees.certificates.update',
            'employees.certificates.destroy',
        ] as $routeName) {
            $route = $routes->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains('operator-or-administrator', $route->gatherMiddleware());
        }

        $downloadRoute = $routes->getByName('employees.certificates.download');
        $this->assertNotNull($downloadRoute);
        $this->assertNotContains('operator-or-administrator', $downloadRoute->gatherMiddleware());
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
