<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Antrian tinjauan manual untuk pegawai yang ambigu: nama ternormalisasi
 * sama tetapi tidak cukup bukti identitas nasional untuk fusi otomatis.
 * Read-only; keputusan merge/split tetap di tangan operator.
 */
final class EmployeeIdentityReviewService
{
    /** @return Collection<int,array{key:string,members:array<int,array<string,mixed>>}> */
    public function ambiguousGroups(): Collection
    {
        return Employee::query()
            ->whereNotNull('normalized_name')
            ->get()
            ->groupBy('normalized_name')
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(fn (Collection $group, string $key) => [
                'key' => $key,
                'members' => $group->map(fn (Employee $row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'nuptk' => $row->nuptk,
                    'nip' => $row->nip,
                    'nik' => $row->nik,
                    'source_type' => $row->source_type,
                    'operator_locked' => (bool) $row->operator_locked,
                    'dapodik_id' => $row->dapodik_id,
                ])->values()->all(),
            ])
            ->values();
    }
}
