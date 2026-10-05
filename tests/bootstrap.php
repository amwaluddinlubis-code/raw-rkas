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

/*
 * Jaga database/testing.sqlite agar bisa dipakai RefreshDatabase.
 *
 * Dua kegagalan berulang yang sudah tercatat di CURRENT_PROGRESS.md:
 *  - berkas hilang: RefreshDatabase membuka koneksi ke file yang tidak ada dan
 *    setiap test gagal dengan "Database file at path ... does not exist";
 *  - berkas korup: "database disk image is malformed" atau "file is not a
 *    database" (SQLite di Windows).
 *
 * Halaman produksi database/database.sqlite tidak pernah disentuh di sini.
 * Jalur ini hanya aktif lewat phpunit.xml yang memaksa DB_DATABASE ke
 * database/testing.sqlite, dan berkas harus berada persis di lokasi itu.
 */
$testingDatabase = dirname(__DIR__).'/database/testing.sqlite';

if (! is_dir(dirname($testingDatabase)) || ! is_writable(dirname($testingDatabase))) {
    fwrite(STDERR, 'REFUSED: database/ tidak dapat ditulis untuk menyiapkan testing.sqlite.'.PHP_EOL);
    exit(1);
}

// Berkas testing.sqlite sengaja dibuat kosong: RefreshDatabase yang membangun
// skema. Kalau berkas korup, isinya ditulis ulang menjadi nol byte di tempat
// yang sama. Rename tidak dipakai karena PDO sempat membuka handle dan pada
// Windows berkas sqlite tidak bisa di-rename selama masih dirujuk proses.
$resetTestingDatabase = function (string $target, ?string $corruptReason = null): void {
    if ($corruptReason !== null) {
        fwrite(
            STDERR,
            'NOTICE: database/testing.sqlite korup ('.$corruptReason.'); isi berkas dikosongkan dan '
            .'skema akan dibangun ulang oleh RefreshDatabase.'.PHP_EOL
        );
    }

    // comparing === 0: file_put_contents mengembalikan jumlah byte tertulis,
    // jadi 0 berarti gagal dan bukan sekadar berkas kosong.
    if (file_put_contents($target, '') === 0 && filesize($target) !== 0) {
        fwrite(STDERR, 'REFUSED: database/testing.sqlite tidak dapat ditulis.'.PHP_EOL);
        exit(1);
    }
};

if (! file_exists($testingDatabase)) {
    $resetTestingDatabase($testingDatabase);
} else {
    $integrity = null;

    try {
        // Error mode exception wajib: tanpa itu PDO diam-diam mengembalikan
        // false untuk "file is not a database".
        $pdo = new PDO('sqlite:'.$testingDatabase, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
        $pdo = null;
    } catch (Throwable $exception) {
        $integrity = 'error: '.$exception->getMessage();
    }

    if ($integrity !== 'ok') {
        $resetTestingDatabase($testingDatabase, (string) var_export($integrity, true));
    }
}
