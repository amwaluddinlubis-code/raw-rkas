<?php

namespace App\Console\Commands;

use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Resolusi sekolah target untuk command tenant: --school=NPSN atau --all.
 *
 * Dipakai bersama oleh command fuse-duplicates agar logika pemilihan
 * tenant hanya ada di satu tempat.
 */
trait ResolvesTenantSchools
{
    private function resolveSchools(): ?Collection
    {
        if ((bool) $this->option('all')) {
            return School::query()->with('databaseRecord')->get()->filter(
                fn (School $school) => $school->databaseRecord && File::exists($school->databaseRecord->database_path)
            )->values();
        }

        $npsn = trim((string) ($this->option('school') ?? ''));
        if ($npsn === '') {
            $this->error('Isi --school=NPSN atau gunakan --all.');

            return null;
        }

        $school = School::query()->with('databaseRecord')->where('npsn', $npsn)->first();
        if (! $school) {
            $this->error('Sekolah dengan NPSN '.$npsn.' tidak ditemukan pada database utama.');

            return null;
        }

        if (! $school->databaseRecord || ! File::exists($school->databaseRecord->database_path)) {
            $this->error('File database tenant untuk '.$school->name.' tidak ditemukan. Perintah ini tidak membuat database baru.');

            return null;
        }

        return collect([$school]);
    }
}
