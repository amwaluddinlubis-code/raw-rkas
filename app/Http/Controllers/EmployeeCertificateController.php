<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Services\OperationalAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeCertificateController extends Controller
{
    public function store(Request $request, int $employeeId, OperationalAuditService $audit): RedirectResponse
    {
        $employee = Employee::findOrFail($employeeId);
        $data = $this->validated($request);

        $certificate = $employee->certificates()->create($data + ['created_by' => auth()->id()]);
        $audit->record(
            session('active_fiscal_year_id'),
            'EMPLOYEE_SK',
            $certificate->id,
            'TAMBAH',
            'SK '.EmployeeCertificate::label($certificate->kind).' ditambahkan untuk '.$employee->name.'.'
        );

        return redirect()->route('employees.show', $employee)->with('success', 'SK berhasil ditambahkan.');
    }

    public function update(Request $request, int $certificateId, OperationalAuditService $audit): RedirectResponse
    {
        $certificate = EmployeeCertificate::with('employee')->findOrFail($certificateId);
        $certificate->update($this->validated($request));
        $audit->record(
            session('active_fiscal_year_id'),
            'EMPLOYEE_SK',
            $certificate->id,
            'UBAH',
            'SK '.EmployeeCertificate::label($certificate->kind).' milik '.$certificate->employee->name.' diperbarui.'
        );

        return redirect()->route('employees.show', $certificate->employee)->with('success', 'SK berhasil diperbarui.');
    }

    public function destroy(int $certificateId, OperationalAuditService $audit): RedirectResponse
    {
        $certificate = EmployeeCertificate::with('employee')->findOrFail($certificateId);
        $employee = $certificate->employee;
        $label = EmployeeCertificate::label($certificate->kind).($certificate->number ? ' '.$certificate->number : '');
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

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', 'string', Rule::in(array_keys(EmployeeCertificate::kinds()))],
            'number' => ['nullable', 'string', 'max:120'],
            'issued_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
