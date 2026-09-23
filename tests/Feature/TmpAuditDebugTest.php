<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class TmpAuditDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_dump_audit_failure(): void
    {
        $school = School::create(['npsn' => '10208183', 'name' => 'SDN Audit']);
        $path = storage_path('framework/testing/tmp-audit-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($path));
        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE fiscal_years (id INTEGER PRIMARY KEY, year INTEGER NOT NULL); INSERT INTO fiscal_years (id, year) VALUES (1, 2026); CREATE TABLE transactions (id INTEGER PRIMARY KEY, fiscal_year_id INTEGER NOT NULL); CREATE TABLE spj_packages (id INTEGER PRIMARY KEY);");
        SchoolDatabase::create(['school_id' => $school->id, 'database_path' => $path, 'status' => 'READY']);

        putenv('SPJ_DEBUG_TRACE=1');
        $exit = Artisan::call('spj:audit-quarter', ['npsn' => '10208183', '--quarter' => 1, '--year' => 2026]);
        fwrite(STDERR, "\nEXIT=".$exit."\nOUTPUT=".Artisan::output()."\n");
        putenv('SPJ_DEBUG_TRACE');
        File::delete($path);
        $this->assertTrue(true);
    }
}
