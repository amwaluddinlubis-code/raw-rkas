<?php

return [
    'arkas_bridge_command' => env('SPJ_ARKAS_BRIDGE_COMMAND'),
    'xlsx_pdf_renderer' => env('SPJ_XLSX_PDF_RENDERER', 'libreoffice'),
    'school_mode' => env('SPJ_SCHOOL_MODE', 'single-school'),
    'fiscal_year_mode' => env('SPJ_FISCAL_YEAR_MODE', 'single-year'),
    'backup_retention' => (int) env('SPJ_BACKUP_RETENTION', 30),
    'data_path' => env('SPJ_DATA_PATH', storage_path('app')),
    /*
    | Root folder database sekolah. Bila SPJ_DATA_PATH sudah menunjuk
    | langsung ke folder school-databases* (mis. .../school-databases-arkas-mirror),
    | pakai apa adanya; bila menunjuk parent, append /school-databases.
    */
    'databases_root' => (function (): string {
        $base = rtrim((string) env('SPJ_DATA_PATH', storage_path('app')), '/\\');

        return str_starts_with(strtolower(basename($base)), 'school-databases')
            ? $base
            : $base.DIRECTORY_SEPARATOR.'school-databases';
    })(),
    'database_manager_sensitive_columns' => [
        'password',
        'token',
        'secret',
        'key',
        'credential',
    ],
    /*
    | Lebar kolom (persen, array) untuk laporan cetak Laporan Periode.
    | Kustomisasi cukup dari kunci ini tanpa mengubah Blade; kunci yang tidak
    | diisi memakai nilai default pada partial masing-masing.
    */
    'periodic_report_column_widths' => [
        'rekap_bosp' => [
            'no' => '4%',
            'standar_nasional' => '16%',
            'sub_program' => '8%',
            'jumlah' => '8%',
            'identity' => ['20%', '80%'],
            'meta' => ['25%', '75%'],
        ],
        'bpk_bos' => [
            'meta' => ['30%', '70%'],
            'table' => ['6%', '40%', '12%', '14%', '14%', '14%'],
            'signature' => ['50%', '50%'],
        ],
    ],
];
