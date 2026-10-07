<?php

namespace Tests\Feature;

use App\Livewire\DashboardWorkspace;
use App\Livewire\DatabaseMaintenance;
use App\Livewire\DatabaseResetForm;
use App\Livewire\DatabaseSchoolList;
use App\Livewire\DocumentStorageSettings;
use App\Livewire\SchoolMaster;
use App\Livewire\TransactionDetailWorkspace;
use App\Livewire\UserManagement;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\SpjSourceReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class LivewireMutationAuthorizationTest extends TestCase
{
    use RefreshDatabase, SeedsArkasMirror;

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_operator_and_viewer_cannot_invoke_administrator_livewire_mutations(): void
    {
        $school = School::query()->create([
            'school_code' => 'AUTH-001',
            'npsn' => '40000001',
            'name' => 'SD Authorization',
        ]);
        $targetAdmin = User::factory()->create([
            'name' => 'Target Administrator',
            'email' => 'target-admin@example.test',
            'role' => User::ROLE_ADMIN,
        ]);
        $victim = User::factory()->create([
            'name' => 'Protected Operator',
            'email' => 'protected-operator@example.test',
            'role' => User::ROLE_OPERATOR,
            'school_id' => $school->id,
        ]);

        foreach ([User::ROLE_OPERATOR, User::ROLE_VIEWER] as $role) {
            $actor = User::factory()->create([
                'role' => $role,
                'school_id' => $school->id,
            ]);

            Livewire::actingAs($actor)
                ->test(UserManagement::class)
                ->call('createUser')
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(UserManagement::class)
                ->call(
                    'updateUser',
                    $targetAdmin->id,
                    'Tampered Administrator',
                    'tampered-admin@example.test',
                    User::ROLE_ADMIN,
                )
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(UserManagement::class)
                ->call('deleteUser', $victim->id)
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(SchoolMaster::class)
                ->call('createSchool')
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(DatabaseMaintenance::class)
                ->call('run', 'checkpoint')
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(DatabaseResetForm::class, [
                    'active' => ['school' => $school, 'database' => '—'],
                ])
                ->call('resetDatabase')
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(DatabaseSchoolList::class)
                ->call('activate', $school->id)
                ->assertStatus(403);

            Livewire::actingAs($actor)
                ->test(DatabaseSchoolList::class)
                ->call('migrate', $school->id)
                ->assertStatus(403);
        }

        $this->assertDatabaseHas('users', [
            'id' => $targetAdmin->id,
            'name' => 'Target Administrator',
            'email' => 'target-admin@example.test',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $victim->id,
            'email' => 'protected-operator@example.test',
        ]);
        $this->assertDatabaseCount('schools', 1);
    }

    public function test_document_storage_livewire_mutation_allows_operator_and_rejects_viewer(): void
    {
        $directory = storage_path('framework/testing/livewire-storage-'.uniqid());
        File::ensureDirectoryExists($directory);

        try {
            $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

            Livewire::actingAs($operator)
                ->test(DocumentStorageSettings::class)
                ->set('path', $directory)
                ->call('save')
                ->assertHasNoErrors();

            $this->assertDatabaseHas('app_settings', [
                'key' => 'document_storage_path',
                'value' => $directory,
            ]);

            $viewer = User::factory()->create(['role' => User::ROLE_VIEWER]);

            Livewire::actingAs($viewer)
                ->test(DocumentStorageSettings::class)
                ->set('path', $directory)
                ->call('save')
                ->assertStatus(403);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_viewer_is_forbidden_from_dashboard_mark_ready(): void
    {
        $context = $this->prepareSpjWorkspaceContext();

        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'school_id' => $context['school']->id,
        ]);

        Livewire::actingAs($viewer)
            ->test(DashboardWorkspace::class)
            ->call('markReady', (string) $context['package']->id)
            ->assertStatus(403);
    }

    public function test_operator_can_mark_ready_from_dashboard(): void
    {
        $context = $this->prepareSpjWorkspaceContext();

        $operator = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'school_id' => $context['school']->id,
        ]);

        Livewire::actingAs($operator)
            ->test(DashboardWorkspace::class)
            ->call('markReady', (string) $context['package']->id)
            ->assertOk();
    }

    public function test_viewer_is_forbidden_from_transaction_detail_mutations(): void
    {
        $context = $this->prepareSpjWorkspaceContext();

        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'school_id' => $context['school']->id,
        ]);

        Livewire::actingAs($viewer)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $context['transaction']->id])
            ->call('saveDescriptions')
            ->assertStatus(403);

        Livewire::actingAs($viewer)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $context['transaction']->id])
            ->call('resolveReconciliation', null)
            ->assertStatus(403);

        Livewire::actingAs($viewer)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $context['transaction']->id])
            ->call('dismissArtifacts')
            ->assertStatus(403);

        Livewire::actingAs($viewer)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $context['transaction']->id])
            ->call('moveItem', $context['item']->id, 'down')
            ->assertStatus(403);
    }

    public function test_operator_can_invoke_transaction_detail_mutations(): void
    {
        $context = $this->prepareSpjWorkspaceContext();

        $operator = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'school_id' => $context['school']->id,
        ]);

        Livewire::actingAs($operator)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $context['transaction']->id])
            ->call('saveDescriptions')
            ->assertOk()
            ->assertHasNoErrors();

        Livewire::actingAs($operator)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $context['transaction']->id])
            ->call(
                'resolveReconciliation',
                SpjSourceReconciliationService::REVIEWED_NO_BUSINESS_CHANGE,
            )
            ->assertOk();

        Livewire::actingAs($operator)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $context['transaction']->id])
            ->call('dismissArtifacts')
            ->assertOk();

        Livewire::actingAs($operator)
            ->test(TransactionDetailWorkspace::class, ['transactionId' => $context['transaction']->id])
            ->call('moveItem', $context['item']->id, 'down')
            ->assertOk();
    }

    /**
     * Siapkan konteks SPJ minimal (koneksi sekolah in-memory + sesi aktif)
     * agar mount workspace Livewire lolos penjagaan konteks tenant.
     *
     * @return array{school: School, year: FiscalYear, fund: FundSource, transaction: Transaction, item: TransactionItem, package: SpjPackage}
     */
    private function prepareSpjWorkspaceContext(): array
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        $fund = FundSource::query()->create(['code' => 'BOS', 'name' => 'BOS Reguler']);
        $year = FiscalYear::query()->create([
            'year' => 2026,
            'fund_source' => 'BOS Reguler',
            'fund_source_id' => $fund->id,
            'is_active' => true,
        ]);
        $school = School::query()->create([
            'school_code' => 'AUTH-WS',
            'npsn' => '40000002',
            'name' => 'SD Workspace Auth',
        ]);

        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => $year->id,
            'fund_source_id' => $fund->id,
            'no_bukti' => 'BKU-WS-001',
            'transaction_date' => '2026-01-10',
            'description' => 'Belanja ATK',
            'gross_amount' => 100000,
            'tax_total' => 0,
            'net_amount' => 100000,
        ]);
        $item = $this->mirrorItem($transaction, ['description' => 'Kertas A4', 'amount' => 60000]);
        $this->mirrorItem($transaction, ['description' => 'Tinta printer', 'amount' => 40000]);
        $package = SpjPackage::query()->create([
            'transaction_id' => $transaction->id,
            'status' => 'DRAFT',
        ]);

        $this->withSession([
            'active_school_id' => $school->id,
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $fund->id,
        ]);

        return [
            'school' => $school,
            'year' => $year,
            'fund' => $fund,
            'transaction' => $transaction,
            'item' => $item,
            'package' => $package,
        ];
    }
}
