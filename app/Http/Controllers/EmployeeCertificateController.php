<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Services\DocumentStoragePathService;
use App\Services\OperationalAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeCertificateController extends Controller
{
    public function store(Request $request, int $employeeId, OperationalAuditService $audit, DocumentStoragePathService $storage): RedirectResponse
    {
        $employee = Employee::findOrFail($employeeId);
        $data = $this->validated($request);

        $certificate = $employee->certificates()->create($data + ['created_by' => auth()->id()]);
        if ($request->hasFile('file')) {
            $this->storeFile($request, $certificate, $storage);
        }
        $audit->record(
            session('active_fiscal_year_id'),
            'EMPLOYEE_SK',
            $certificate->id,
            'TAMBAH',
            'SK '.EmployeeCertificate::label($certificate->kind).' ditambahkan untuk '.$employee->name.'.'
        );

        return redirect()->route('employees.show', $employee)->with('success', 'SK berhasil ditambahkan.');
    }

    public function update(Request $request, int $certificateId, OperationalAuditService $audit, DocumentStoragePathService $storage): RedirectResponse
    {
        $certificate = EmployeeCertificate::with('employee')->findOrFail($certificateId);
        $certificate->update($this->validated($request));
        if ($request->hasFile('file')) {
            $storage->deleteEmployeeCertificateFile($certificate->file_path);
            $this->storeFile($request, $certificate, $storage);
        }
        $audit->record(
            session('active_fiscal_year_id'),
            'EMPLOYEE_SK',
            $certificate->id,
            'UBAH',
            'SK '.EmployeeCertificate::label($certificate->kind).' milik '.$certificate->employee->name.' diperbarui.'
        );

        return redirect()->route('employees.show', $certificate->employee)->with('success', 'SK berhasil diperbarui.');
    }

    public function destroy(int $certificateId, OperationalAuditService $audit, DocumentStoragePathService $storage): RedirectResponse
    {
        $certificate = EmployeeCertificate::with('employee')->findOrFail($certificateId);
        $employee = $certificate->employee;
        $label = EmployeeCertificate::label($certificate->kind).($certificate->number ? ' '.$certificate->number : '');
        $storage->deleteEmployeeCertificateFile($certificate->file_path);
        $certificate->delete();
        $audit->record(
            session('active_fiscal_year_id'),
            'EMPLOYEE_SK',
            $certificateId,
            'HAPUS',
            'SK '.$label.' milik '.$employee->name.' dihapus.'
        );

        return redirect()->route('employees.show', $employee)->with('success', 'SK berhasil dihapus.');
    }

    public function download(int $certificateId, DocumentStoragePathService $storage): BinaryFileResponse
    {
        $certificate = EmployeeCertificate::findOrFail($certificateId);
        if (! $certificate->file_path) {
            abort(404);
        }

        return $storage->downloadEmployeeCertificate(
            $certificate->file_path,
            $certificate->file_name ?: basename($certificate->file_path)
        );
    }

    private function storeFile(Request $request, EmployeeCertificate $certificate, DocumentStoragePathService $storage): void
    {
        $file = $request->file('file');
        $stored = $storage->persistEmployeeCertificate(
            $file->getRealPath(),
            $certificate->employee,
            $certificate->kind,
            $file->getClientOriginalName()
        );
        $certificate->forceFill([
            'file_path' => $stored,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
        ])->save();
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', 'string', Rule::in(array_keys(EmployeeCertificate::kinds()))],
            'number' => ['nullable', 'string', 'max:120'],
            'issued_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'extensions:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
    }
}
