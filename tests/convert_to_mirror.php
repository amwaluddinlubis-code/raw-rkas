<?php

// Konversi create transaksi/item test ke trait SeedsArkasMirror.
// Hanya mengganti PREFIX panggilan (seimbang tanda kurung).
$files = glob(__DIR__.'/Feature/*.php');
$report = [];

foreach ($files as $f) {
    if (str_contains($f, 'ArkasMirrorSourceValueTest')) {
        continue;
    }

    $s = file_get_contents($f);
    $o = $s;

    $hasTxCreate = str_contains($s, 'Transaction::query()->create(')
        || str_contains($s, 'Transaction::create(');
    $hasItemCreate = str_contains($s, '->items()->create(');

    if (! $hasTxCreate && ! $hasItemCreate) {
        continue;
    }

    if (! str_contains($s, 'SeedsArkasMirror')) {
        if (str_contains($s, 'use RefreshDatabase;')) {
            $s = str_replace('use RefreshDatabase;', 'use RefreshDatabase, SeedsArkasMirror;', $s);
        } else {
            $s = preg_replace(
                '/(class\s+\w+\s+extends\s+TestCase\s*\{\n)/',
                '$1    use SeedsArkasMirror;'.PHP_EOL.PHP_EOL,
                $s,
                1
            );
        }
        $s = str_replace(
            'use Tests\\TestCase;',
            'use Tests\\Support\\SeedsArkasMirror;'.PHP_EOL.'use Tests\\TestCase;',
            $s
        );
    }

    $s = str_replace('Transaction::query()->create(', '$this->mirrorTransaction(', $s);
    $s = str_replace('Transaction::create(', '$this->mirrorTransaction(', $s);
    $s = preg_replace(
        '/(\$[A-Za-z_][A-Za-z0-9_]*)->items\(\)->create\(/',
        '$this->mirrorItem($1, ',
        $s
    );

    if ($s !== $o) {
        file_put_contents($f, $s);
        $report[] = basename($f);
    }
}

echo 'converted='.count($report).PHP_EOL.implode(PHP_EOL, $report).PHP_EOL;
