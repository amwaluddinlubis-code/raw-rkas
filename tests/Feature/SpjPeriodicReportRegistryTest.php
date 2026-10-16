<?php

namespace Tests\Feature;

use App\Services\SpjPeriodicReportRegistry;
use Tests\TestCase;

class SpjPeriodicReportRegistryTest extends TestCase
{
    public function test_registry_contains_requested_report_packages(): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);
        $packages = $registry->packages();

        $this->assertSame(10, count($packages['bulan']));
        $this->assertSame(10, count($packages['triwulan']));
        $this->assertSame(8, count($packages['semester']));
        $this->assertSame(7, count($packages['tahunan']));

        $this->assertSame([
            'SPTJM',
            'Buku Kas Umum',
            'Buku Pembantu Kas',
            'Buku Pembantu Bank',
            'Buku Pembantu Pajak',
            'Register Penutupan Kas (K7B)',
            'Berita Acara Pemeriksaan Kas (K7C)',
            'Rekap Belanja Modal dan Belanja Barang / Jasa',
            'Rekapitulasi Pengeluaran Dana BOS',
            'Laporan BPK Format BOS',
        ], array_column($packages['bulan'], 'label'));

        $this->assertSame([
            'SPTJM',
            'Format K7',
            'Format BOS K-2',
            'RPD BOS',
            'Buku Kas Umum (BKU)',
            'Berita Acara Rekonsiliasi',
            'Lampiran Berita Acara Rekonsiliasi',
            'Rekapitulasi Pengeluaran Dana BOS',
            'Laporan BPK Format BOS',
            'Format BOS A-1',
        ], array_column($packages['triwulan'], 'label'));

        $this->assertSame([
            'SPTJM',
            'Format K7',
            'Buku Kas Umum (BKU)',
            'Berita Acara Rekonsiliasi',
            'Lampiran Berita Acara Rekonsiliasi',
            'Rekapitulasi Pengeluaran Dana BOS',
            'Rekap Belanja Barang Milik Daerah (Aset)',
            'Laporan BPK Format BOS',
        ], array_column($packages['semester'], 'label'));

        $this->assertSame([
            'SPTJM',
            'Format K7',
            'Buku Kas Umum (BKU)',
            'Rekap Barang Milik Daerah (Aset)',
            'Rekap Belanja Dana BOS',
            'Rekapitulasi Pengeluaran Dana BOS',
            'Laporan BPK Format BOS',
        ], array_column($packages['tahunan'], 'label'));
    }

    public function test_period_contract_is_explicit_for_each_scope(): void
    {
        $registry = app(SpjPeriodicReportRegistry::class);

        $this->assertTrue($registry->isValidPeriod('bulan', 12));
        $this->assertFalse($registry->isValidPeriod('bulan', 13));
        $this->assertTrue($registry->isValidPeriod('triwulan', 4));
        $this->assertFalse($registry->isValidPeriod('triwulan', 5));
        $this->assertTrue($registry->isValidPeriod('semester', 2));
        $this->assertFalse($registry->isValidPeriod('semester', 3));
        $this->assertTrue($registry->isValidPeriod('tahunan', null));
        $this->assertFalse($registry->isValidPeriod('tahunan', 1));
    }
}
