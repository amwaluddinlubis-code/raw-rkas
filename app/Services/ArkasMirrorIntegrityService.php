<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostik read-only atas konsistensi mirror ARKAS pada konteks
 * tahun anggaran + sumber dana aktif. Tidak mengubah data source;
 * hanya menyorot baris yang tidak punya pasangan referensi sehingga
 * operator tahu pagu/realisasi dihitung dari subset data sehat.
 */
final class ArkasMirrorIntegrityService
{
    /** @return array{ok:bool,checks:array<int,array{label:string,count:int,ok:bool,hint:string}>} */
    public function summarize(int $yearId, int $fundSourceId): array
    {
        $db = DB::connection('school');
        if (! Schema::connection('school')->hasTable('arkas_mirror_rapbs')) {
            return ['ok' => true, 'checks' => []];
        }

        $anggaranYear = [];
        $anggaranFund = [];
        if (Schema::connection('school')->hasTable('arkas_mirror_anggaran')) {
            foreach ($db->table('arkas_mirror_anggaran')->get(['source_key', 'payload']) as $row) {
                $payload = $this->norm($row->payload);
                if (! is_array($payload) || (int) ($payload['SOFT_DELETE'] ?? 0) === 1) {
                    continue;
                }
                $id = (string) ($payload['ID_ANGGARAN'] ?? $row->source_key);
                $anggaranYear[$id] = (int) ($payload['TAHUN_ANGGARAN'] ?? $payload['TAHUN'] ?? 0);
                $anggaranFund[$id] = (int) ($payload['ID_REF_SUMBER_DANA'] ?? $payload['SUMBER_DANA_ID'] ?? 0);
            }
        }

        $rapbsYear = [];
        $rapbsFund = [];
        $rapbsColumns = ['source_key', 'payload'];
        $hasAnggaranColumn = Schema::connection('school')->hasColumn('arkas_mirror_rapbs', 'sx_id_anggaran');
        if ($hasAnggaranColumn) {
            $rapbsColumns[] = 'sx_id_anggaran';
        }
        foreach ($db->table('arkas_mirror_rapbs')->get($rapbsColumns) as $row) {
            $payload = $this->norm($row->payload);
            if (! is_array($payload) || (int) ($payload['SOFT_DELETE'] ?? 0) === 1) {
                continue;
            }
            $anggaranId = (string) ($payload['ID_ANGGARAN'] ?? ($hasAnggaranColumn ? ($row->sx_id_anggaran ?? '') : '') ?: '');
            $rapbsYear[$row->source_key] = $anggaranYear[$anggaranId] ?? 0;
            $rapbsFund[$row->source_key] = $anggaranFund[$anggaranId] ?? 0;
        }

        $periodRapbs = [];
        $orphanPeriods = 0;
        foreach ($db->table('arkas_mirror_rapbs_periode')->get(['payload']) as $row) {
            $payload = $this->norm($row->payload);
            if (! is_array($payload) || (int) ($payload['SOFT_DELETE'] ?? 0) === 1) {
                continue;
            }
            $rapbsId = (string) ($payload['ID_RAPBS'] ?? '');
            if ($rapbsId === '' || ! isset($rapbsYear[$rapbsId])) {
                $orphanPeriods++;

                continue;
            }
            $periodRapbs[$rapbsId] = true;
        }

        $kasWithoutRapbs = 0;
        $kasOtherYear = 0;
        foreach ($db->table('arkas_mirror_kas_umum')->get(['payload']) as $row) {
            $payload = $this->norm($row->payload);
            if (! is_array($payload) || strtoupper((string) ($payload['KATEGORI_BKU'] ?? '')) !== 'BELANJA') {
                continue;
            }
            $fund = $payload['ID_REF_SUMBER_DANA'] ?? $payload['SUMBER_DANA_ID'] ?? null;
            if ($fund !== null && $fund !== '' && (int) $fund !== $fundSourceId) {
                continue;
            }
            $rapbsId = (string) ($payload['ID_RAPBS'] ?? '');
            if ($rapbsId === '' || ! isset($rapbsYear[$rapbsId])) {
                $kasWithoutRapbs++;

                continue;
            }
            if (($rapbsYear[$rapbsId] > 0 && $rapbsYear[$rapbsId] !== $yearId)
                || ($rapbsFund[$rapbsId] > 0 && $rapbsFund[$rapbsId] !== $fundSourceId)) {
                $kasOtherYear++;
            }
        }

        $rapbsAnggaranIds = [];
        foreach ($db->table('arkas_mirror_rapbs')->get($rapbsColumns) as $row) {
            $payload = $this->norm($row->payload);
            if (! is_array($payload) || (int) ($payload['SOFT_DELETE'] ?? 0) === 1) {
                continue;
            }
            $anggaranId = (string) ($payload['ID_ANGGARAN'] ?? ($hasAnggaranColumn ? ($row->sx_id_anggaran ?? '') : '') ?: '');
            if ($anggaranId !== '') {
                $rapbsAnggaranIds[$anggaranId] = true;
            }
        }

        $anggaranWithoutRapbs = 0;
        foreach ($anggaranYear as $id => $year) {
            $fund = $anggaranFund[$id] ?? 0;
            if (($year > 0 && $year !== $yearId) || ($fund > 0 && $fund !== $fundSourceId)) {
                continue;
            }
            if (! isset($rapbsAnggaranIds[$id])) {
                $anggaranWithoutRapbs++;
            }
        }

        $checks = [
            ['label' => 'Kas tanpa baris RKAS', 'count' => $kasWithoutRapbs, 'ok' => $kasWithoutRapbs === 0, 'hint' => 'Kas BELANJA pada sumber dana aktif yang ID_RAPBS-nya tidak ditemukan di mirror RKAS. Realisasi tidak dapat diatribusikan ke pagu.'],
            ['label' => 'Kas tertaut ke RKAS tahun/dana lain (tidak dihitung)', 'count' => $kasOtherYear, 'ok' => true, 'hint' => 'Kas sumber dana aktif yang menunjuk RKAS pada tahun anggaran atau sumber dana lain; sengaja tidak dimasukkan pada total tahun berjalan.'],
            ['label' => 'Periode RKAS yatim', 'count' => $orphanPeriods, 'ok' => $orphanPeriods === 0, 'hint' => 'Baris periode yang ID_RAPBS-nya tidak ditemukan di mirror RKAS; alokasi periode tidak dapat dipakai.'],
            ['label' => 'Anggaran tersetujui tanpa RKAS', 'count' => $anggaranWithoutRapbs, 'ok' => $anggaranWithoutRapbs === 0, 'hint' => 'Revisi anggaran aktif yang belum memiliki baris RKAS pada sumber dana/tahun aktif.'],
        ];

        return ['ok' => collect($checks)->every(fn ($c) => $c['ok']), 'checks' => $checks];
    }

    /** @return array<string,mixed>|null */
    private function norm(mixed $payload): ?array
    {
        $decoded = is_array($payload) ? $payload : json_decode((string) $payload, true);

        return is_array($decoded) ? array_change_key_case($decoded, CASE_UPPER) : null;
    }
}
