<?php

namespace App\UseCases\Spj;

use App\Models\ArkasSource;
use App\Models\School;
use App\Services\ArkasFixedMirrorService;
use App\Services\SchoolDatabaseManager;

class SynchronizeArkasMirrorUseCase
{
    public function __construct(
        private readonly ArkasFixedMirrorService $mirror,
        private readonly SchoolDatabaseManager $databases,
    ) {}

    /**
     * @param  'all'|'refs'|'school'  $scope
     * @return array{read: int, written: int, new: int, changed: int, unchanged: int, tables: array<string, array{read: int, written: int, new: int, changed: int, unchanged: int}>}
     */
    public function execute(School $school, ?int $fiscalYearId = null, string $scope = 'all', ?\Closure $onProgress = null): array
    {
        $this->databases->activate($school);

        $source = ArkasSource::query()->where('school_id', $school->id)->first();

        if (! $source) {
            throw new \RuntimeException('Sumber ARKAS untuk sekolah ini belum disimpan. Isi path database dan kata sandi terlebih dahulu.');
        }

        return match ($scope) {
            'refs' => $this->mirror->syncCentral($source, $onProgress),
            'school' => $this->mirror->syncSchool($source, $fiscalYearId, $onProgress),
            default => $this->mirror->syncAll($source, $fiscalYearId, $onProgress),
        };
    }
}
