<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanggung Jawab Kegiatan per paket SPJ: snapshot nama + NIP pegawai
     * yang dipilih dari daftar pegawai (employees). employee_id disimpan
     * sebagai jejak asal pilihan; dokumen membaca snapshot nama/NIP agar
     * tidak berubah bila data pegawai diperbarui kemudian.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('transactions', 'person_in_charge_name')) {
                $table->string('person_in_charge_name')->nullable()->after('receipt_recipient_name');
            }
            if (! Schema::hasColumn('transactions', 'person_in_charge_nip')) {
                $table->string('person_in_charge_nip')->nullable()->after('person_in_charge_name');
            }
            if (! Schema::hasColumn('transactions', 'person_in_charge_employee_id')) {
                $table->unsignedBigInteger('person_in_charge_employee_id')->nullable()->after('person_in_charge_nip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            foreach (['person_in_charge_employee_id', 'person_in_charge_nip', 'person_in_charge_name'] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
