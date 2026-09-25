<?php

/*
 * PHPUnit bootstrap guard (audit reset database 2026-09-21).
 *
 * JANGAN menjalankan suite ketika config cache aktif: config yang di-cache
 * membuat <env> DB_DATABASE phpunit.xml diabaikan sehingga RefreshDatabase
 * menghajar database.sqlite utama alih-alih database/testing.sqlite.
 */
require __DIR__.'/../vendor/autoload.php';

if (file_exists(__DIR__.'/../bootstrap/cache/config.php')) {
    fwrite(
        STDERR,
        'REFUSED: bootstrap/cache/config.php aktif. Jalankan `php artisan config:clear` sebelum testing agar suite tidak menyentuh database utama.'.PHP_EOL
    );
    exit(1);
}
