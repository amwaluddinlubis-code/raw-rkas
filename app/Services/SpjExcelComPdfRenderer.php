<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class SpjExcelComPdfRenderer
{
    private const LOCK_TIMEOUT_SECONDS = 120;

    private const PROCESS_TIMEOUT_SECONDS = 120;

    public function isAvailable(): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $finder = new ExecutableFinder;
        $powershell = $finder->find('powershell.exe');
        $registry = $finder->find('reg.exe');

        if ($powershell === null || $registry === null) {
            return false;
        }

        $process = new Process([$registry, 'query', 'HKCR\\Excel.Application\\CLSID']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    }

    public function convertFile(string $sourcePath): ?string
    {
        if (! is_file($sourcePath)) {
            return null;
        }

        if (strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new RuntimeException('Renderer Excel COM hanya mendukung berkas XLSX.');
        }

        $powershell = (new ExecutableFinder)->find('powershell.exe');
        if (PHP_OS_FAMILY !== 'Windows' || $powershell === null || ! $this->isAvailable()) {
            throw new RuntimeException('Microsoft Excel COM tidak tersedia pada worker PDF ini.');
        }

        $directory = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'spj-excel-pdf-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori sementara Excel PDF tidak dapat dibuat.');
        }

        $output = $directory.DIRECTORY_SEPARATOR.'rendered.pdf';
        $lockPath = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'spj-excel-com-renderer.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            File::deleteDirectory($directory);

            throw new RuntimeException('Lock renderer Excel COM tidak dapat dibuat.');
        }

        $lockAcquired = false;

        try {
            $deadline = microtime(true) + self::LOCK_TIMEOUT_SECONDS;
            do {
                $lockAcquired = flock($lock, LOCK_EX | LOCK_NB);
                if (! $lockAcquired) {
                    usleep(100_000);
                }
            } while (! $lockAcquired && microtime(true) < $deadline);

            if (! $lockAcquired) {
                throw new RuntimeException('Worker PDF lain masih memakai Excel; batas tunggu konversi terlampaui.');
            }

            $process = new Process([
                $powershell,
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-NonInteractive',
                '-Command',
                $this->commandString($sourcePath, $output),
            ]);
            $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
            $process->run();

            if (! $process->isSuccessful()) {
                $diagnostic = trim($process->getErrorOutput().PHP_EOL.$process->getOutput());
                throw new RuntimeException('Konversi PDF dengan Microsoft Excel gagal: '.$diagnostic);
            }

            $contents = file_get_contents($output);
            if ($contents === false || ! str_starts_with($contents, '%PDF-')) {
                throw new RuntimeException('Microsoft Excel tidak menghasilkan berkas PDF yang valid.');
            }

            return $contents;
        } finally {
            if ($lockAcquired) {
                flock($lock, LOCK_UN);
            }
            fclose($lock);
            File::deleteDirectory($directory);
        }
    }

    private function commandString(string $sourcePath, string $outputPath): string
    {
        $lines = [];
        $lines[] = '$ErrorActionPreference = "Stop"';
        $lines[] = '$sourcePath = '.var_export($sourcePath, true);
        $lines[] = '$outputPath = '.var_export($outputPath, true);
        $lines[] = '$excel = New-Object -ComObject Excel.Application';
        $lines[] = '$excel.Visible = $false';
        $lines[] = '$excel.DisplayAlerts = $false';
        $lines[] = '$excel.EnableEvents = $false';
        $lines[] = '$excel.AutomationSecurity = 3';
        $lines[] = '$excel.AskToUpdateLinks = $false';
        $lines[] = '$workbooks = $excel.Workbooks';
        $lines[] = '$workbook = $workbooks.Open($sourcePath, 0, $true, 5, "", "", $true, 1, "", $false, $false, 0, $false, $true, 0)';
        $lines[] = '$workbook.ExportAsFixedFormat(0, $outputPath, 0, $true, $false)';
        $lines[] = 'if ($null -ne $workbook) { try { $workbook.Close($false) } catch {} };
        [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($workbook)';
        $lines[] = 'if ($null -ne $workbooks) { try { [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($workbooks) } catch {} }';
        $lines[] = 'if ($null -ne $excel) { try { $excel.Quit() } catch {} };
        [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($excel)';
        $lines[] = '[GC]::Collect()';
        $lines[] = '[GC]::WaitForPendingFinalizers()';

        return implode(PHP_EOL, $lines);
    }

    private function encodedCommand(string $sourcePath, string $outputPath): string
    {
        $source = base64_encode($sourcePath);
        $output = base64_encode($outputPath);
        $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$sourcePath = [System.Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__SOURCE__'))
$outputPath = [System.Text.Encoding]::UTF8.GetString([Convert]::FromBase64String('__OUTPUT__'))
$excel = $null
$workbooks = $null
$workbook = $null
$cleanupError = $null
try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false
    $excel.EnableEvents = $false
    $excel.AutomationSecurity = 3
    $excel.AskToUpdateLinks = $false
    $workbooks = $excel.Workbooks
    $workbook = $workbooks.Open($sourcePath, 0, $true, 5, '', '', $true, 1, '', $false, $false, 0, $false, $true, 0)
    $workbook.ExportAsFixedFormat(0, $outputPath, 0, $true, $false)
} catch {
    [Console]::Error.WriteLine($_.Exception.ToString())
    throw
} finally {
    if ($null -ne $workbook) {
        try { $workbook.Close($false) } catch { $cleanupError = $_.Exception }
        try { [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($workbook) } catch { if ($null -eq $cleanupError) { $cleanupError = $_.Exception } }
    }
    if ($null -ne $workbooks) {
        try { [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($workbooks) } catch { if ($null -eq $cleanupError) { $cleanupError = $_.Exception } }
    }
    if ($null -ne $excel) {
        try { $excel.Quit() } catch { if ($null -eq $cleanupError) { $cleanupError = $_.Exception } }
        try { [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($excel) } catch { if ($null -eq $cleanupError) { $cleanupError = $_.Exception } }
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
    if ($null -ne $cleanupError) {
        [Console]::Error.WriteLine($cleanupError.ToString())
        exit 1
    }
}
POWERSHELL;

        $script = str_replace(
            ['__SOURCE__', '__OUTPUT__'],
            [$source, $output],
            $script,
        );

        return base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
    }
}
