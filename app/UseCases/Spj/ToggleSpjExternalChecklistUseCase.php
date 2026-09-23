<?php

namespace App\UseCases\Spj;

use App\Models\SpjExternalChecklistTick;
use App\Models\SpjPackage;
use App\Models\User;
use App\Services\OperationalAuditService;
use App\Services\SpjExternalChecklistPatterns;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ToggleSpjExternalChecklistUseCase
{
    public function __construct(
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function handle(string $packageId, Request $request): RedirectResponse
    {
        $redirect = redirect()->route('spj.checklist', ['packageId' => $packageId, 'pola' => $request->input('pola')]);

        $package = SpjPackage::query()->with('transaction')->find($packageId);
        if (! $package || ! $package->transaction || ! $this->context->matchesTransaction($package->transaction)) {
            return redirect()
                ->route('spj.index', ['tab' => 'persiapan'])
                ->with('error', 'Paket SPJ tidak ditemukan pada konteks sekolah, tahun anggaran, atau sumber dana aktif.');
        }

        $role = $request->user()?->role;
        if (! in_array($role, [User::ROLE_ADMIN, User::ROLE_OPERATOR], true)) {
            return $redirect->with('error', 'Hanya operator atau administrator yang dapat mengubah checklist bukti dukung.');
        }

        if (! $package->isEditable()) {
            return $redirect->with('error', 'Paket sudah tidak dapat diubah. Checklist bukti dukung mengikuti status paket.');
        }

        $itemKey = (string) $request->input('item_key', '');
        if (! SpjExternalChecklistPatterns::isToggleable($itemKey)) {
            return $redirect->with('error', 'Item checklist tidak dikenal.');
        }

        /** @var SpjExternalChecklistTick $tick */
        $tick = SpjExternalChecklistTick::query()->firstOrNew([
            'spj_package_id' => $package->id,
            'item_key' => $itemKey,
        ]);

        $nowChecked = ! (bool) ($tick->exists ? $tick->is_checked : false);
        $tick->is_checked = $nowChecked;
        $tick->checked_by = $nowChecked ? $request->user()?->id : null;
        $tick->checked_at = $nowChecked ? Carbon::now() : null;
        $tick->save();

        $this->audit->record(
            $package->transaction->fiscal_year_id,
            'SPJ_PACKAGE',
            $package->id,
            'CHECKLIST_EKSTERNAL',
            ($nowChecked ? 'Menandai tersedia: ' : 'Membatalkan tanda: ').SpjExternalChecklistPatterns::itemLabel($itemKey).'.',
        );

        return $redirect->with('success', $nowChecked ? 'Item ditandai tersedia.' : 'Tanda item dibatalkan.');
    }
}
