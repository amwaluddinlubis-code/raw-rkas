<?php

namespace App\Jobs;

use App\Models\BackgroundOperation;
use App\Models\School;
use App\UseCases\Spj\SynchronizeArkasMirrorUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SynchronizeArkasMirror implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(public int $operationId, public int $schoolId, public ?int $fiscalYearId = null, public string $scope = 'all') {}

    public function handle(SynchronizeArkasMirrorUseCase $useCase): void
    {
        $operation = BackgroundOperation::query()->findOrFail($this->operationId);
        $label = $this->scope === 'refs' ? 'Mirror referensi ARKAS' : ($this->scope === 'school' ? 'Mirror sekolah ARKAS' : 'Mirror tetap ARKAS');
        $operation->update(['status' => 'RUNNING', 'progress' => 10, 'started_at' => now(), 'message' => $label.' berjalan.']);

        try {
            $school = School::query()->findOrFail($this->schoolId);
            $operationId = $this->operationId;
            $summary = $useCase->execute($school, $this->fiscalYearId, $this->scope, static function (int $done, int $total, string $table) use ($operationId): void {
                BackgroundOperation::query()->whereKey($operationId)->update([
                    'progress' => $total > 0 ? (int) round(10 + ($done / $total) * 85) : 10,
                    'message' => 'Sinkronisasi tabel '.$done.'/'.$total.($table !== '' ? ' ('.$table.')' : '').' …',
                ]);
            });

            $operation->update([
                'status' => 'COMPLETED',
                'progress' => 100,
                'result' => $summary,
                'message' => $label.' selesai: '.$summary['read'].' dibaca, '.$summary['written'].' ditulis.',
                'finished_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $operation->update([
                'status' => 'FAILED',
                'message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }
}
