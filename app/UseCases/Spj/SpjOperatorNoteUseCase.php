<?php

namespace App\UseCases\Spj;

use App\Models\SpjOperatorNote;
use App\Models\SpjPackage;
use App\Models\User;
use App\Services\OperationalAuditService;
use App\Support\ActiveSpjContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Catatan operator per paket (handover shift, pengingat).
 *
 * Metadata murni: tidak memengaruhi validasi, lifecycle, numbering,
 * maupun sync. Setiap tulis/hapus tercatat di audit operasional.
 */
class SpjOperatorNoteUseCase
{
    public function __construct(
        private readonly OperationalAuditService $audit,
        private readonly ActiveSpjContext $context,
    ) {}

    public function store(string $packageId, Request $request): RedirectResponse
    {
        $redirect = redirect()->route('spj.checklist', ['packageId' => $packageId]);
        $package = SpjPackage::query()->with('transaction')->find($packageId);

        if (! $package || ! $package->transaction || ! $this->context->matchesTransaction($package->transaction)) {
            return redirect()
                ->route('spj.index', ['tab' => 'persiapan'])
                ->with('error', 'Paket SPJ tidak ditemukan pada konteks sekolah, tahun anggaran, atau sumber dana aktif.');
        }

        if (! in_array($request->user()?->role, [User::ROLE_ADMIN, User::ROLE_OPERATOR], true)) {
            return $redirect->with('error', 'Hanya operator atau administrator yang dapat menambah catatan.');
        }

        if ($package->status === 'CANCELLED') {
            return $redirect->with('error', 'Paket yang dibatalkan tidak dapat diberi catatan baru.');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);

        if ($package->operatorNotes()->count() >= 100) {
            return $redirect->with('error', 'Batas 100 catatan per paket tercapai. Hapus catatan lama yang sudah tidak relevan.');
        }

        /** @var SpjOperatorNote $note */
        $note = $package->operatorNotes()->create([
            'body' => trim($validated['body']),
            'created_by' => $request->user()?->id,
        ]);

        $this->audit->record(
            $package->transaction->fiscal_year_id,
            'SPJ_PACKAGE',
            $package->id,
            'CATATAN_OPERATOR',
            'Menambah catatan #'.$note->id.': '.mb_substr($note->body, 0, 120).'.',
        );

        return $redirect->with('success', 'Catatan operator tersimpan.');
    }

    public function destroy(string $noteId, Request $request): RedirectResponse
    {
        /** @var SpjOperatorNote|null $note */
        $note = SpjOperatorNote::query()->with('package.transaction')->find($noteId);

        if (! $note || ! $note->package || ! $note->package->transaction
            || ! $this->context->matchesTransaction($note->package->transaction)) {
            return redirect()
                ->route('spj.index', ['tab' => 'persiapan'])
                ->with('error', 'Catatan tidak ditemukan pada konteks aktif.');
        }

        $user = $request->user();
        $isOwner = $note->created_by !== null && (int) $note->created_by === (int) $user?->getKey();
        if (! ($user?->role === User::ROLE_ADMIN || ($user?->role === User::ROLE_OPERATOR && $isOwner))) {
            return redirect()
                ->route('spj.checklist', ['packageId' => $note->spj_package_id])
                ->with('error', 'Hanya penulis catatan atau administrator yang dapat menghapusnya.');
        }

        $packageId = $note->spj_package_id;
        $fiscalYearId = $note->package->transaction->fiscal_year_id;
        $note->delete();

        $this->audit->record(
            $fiscalYearId,
            'SPJ_PACKAGE',
            $packageId,
            'CATATAN_OPERATOR_HAPUS',
            'Menghapus catatan #'.$noteId.'.',
        );

        return redirect()
            ->route('spj.checklist', ['packageId' => $packageId])
            ->with('success', 'Catatan dihapus.');
    }
}
