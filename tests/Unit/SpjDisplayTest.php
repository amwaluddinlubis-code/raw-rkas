<?php

namespace Tests\\Unit;

use App\\Support\\SpjDisplay;
use PHPUnit\\Framework\\Attributes\\Test;
use Tests\\TestCase;

class SpjDisplayTest extends TestCase
{
    #[Test]
    public function it_formats_rupiah_consistently_for_report_exports(): void
    {
        $this->assertSame('Rp 0', SpjDisplay::rupiah(0));
        $this->assertSame('Rp 1.234', SpjDisplay::rupiah(1234));
        $this->assertSame('Rp -1.234', SpjDisplay::rupiah(-1234));
        $this->assertSame('Rp 1.235', SpjDisplay::rupiah(1234.5));
    }

    #[Test]
    public function it_maps_canonical_and_legacy_spj_categories_to_shared_labels(): void
    {
        $this->assertSame('Honor Pegawai', SpjDisplay::typeLabel('JASA_HONORARIUM'));
        $this->assertSame('Honor Pegawai', SpjDisplay::typeLabel('honor_pegawai'));
        $this->assertSame('Jasa Lainnya', SpjDisplay::typeLabel('JASA_LAINNYA'));
        $this->assertSame('Pemeliharaan', SpjDisplay::typeLabel('pemeliharaan'));
        $this->assertSame('Kategori Baru', SpjDisplay::typeLabel('KATEGORI_BARU'));
    }
}
