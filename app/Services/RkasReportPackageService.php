<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;
use ZipArchive;

final class RkasReportPackageService
{
    public function __construct(
        private readonly RkasReportService $reports,
        private readonly RkasReportExcelService $excel,
    ) {}

    /**
     * @param  array<int,string>  $scopes
     * @param  array<int,string>  $formats
     * @param  array<string,mixed>  $options
     * @return array{path:string,file_name:string}
     */
    public function export(array $scopes, array $formats, array $options): array
    {
        $directory = storage_path('app/rkas-report-packages');
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('Folder sementara paket laporan tidak dapat dibuat.');
        }

        $path = tempnam($directory, 'rkas-');
        if ($path === false) {
            throw new RuntimeException('File sementara paket laporan tidak dapat dibuat.');
        }
        @unlink($path);

        $archive = new ZipArchive;
        $opened = $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            @unlink($path);
            throw new RuntimeException('Arsip paket laporan tidak dapat dibuka.');
        }

        $files = [];
        $excelPaths = [];
        $context = null;
        try {
            foreach ($scopes as $scope) {
                $reportOptions = $options;
                $payload = $this->reports->build($scope, $reportOptions);
                if ($payload === null) {
                    throw new RuntimeException('Data laporan '.$scope.' belum tersedia untuk konteks yang dipilih.');
                }
                $context ??= $payload;

                foreach ($formats as $format) {
                    if ($format === 'pdf') {
                        $contents = Pdf::loadView('rkas-reports.pdf', $payload)
                            ->setPaper('folio', 'landscape')
                            ->output();
                        $name = $payload['file_name'].'.pdf';
                    } else {
                        $excelPath = $this->excel->export($payload);
                        $excelPaths[] = $excelPath;
                        $contents = file_get_contents($excelPath);
                        if ($contents === false) {
                            throw new RuntimeException('File Excel laporan tidak dapat dibaca.');
                        }
                        $name = $payload['file_name'].'.xlsx';
                    }

                    if (! $archive->addFromString($name, $contents)) {
                        throw new RuntimeException('Laporan '.$name.' gagal ditambahkan ke paket.');
                    }
                    $files[] = $name;
                }
            }

            if (! $archive->addFromString('INFO-PAKET.txt', implode("\n", [
                'Paket laporan Kertas Kerja RKAS',
                'Sekolah: '.($context['school']['name'] ?? $options['school_id']),
                'Tahun anggaran: '.($context['year'] ?? ''),
                'Sumber dana: '.($context['fund_name'] ?? $options['fund_source_id']),
                'Revisi: '.($context['revision_label'] ?? 'Pengesahan terakhir'),
                'Tanggal revisi: '.($context['revision_date'] ?? '-'),
                'Dibuat: '.now()->format('d-m-Y H:i:s'),
                '',
                'Isi paket:',
                ...$files,
            ]))) {
                throw new RuntimeException('Manifest paket laporan gagal ditambahkan.');
            }
            $archive->close();
        } catch (\Throwable $exception) {
            $archive->close();
            @unlink($path);
            throw $exception;
        } finally {
            foreach ($excelPaths as $excelPath) {
                @unlink($excelPath);
            }
        }

        $year = (int) ($context['year'] ?? now()->year);

        return ['path' => $path, 'file_name' => 'PAKET-LAPORAN-RKAS-'.$year];
    }
}
