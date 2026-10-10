<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Ekstrak teks gabungan dari bagian document/header/footer XML di dalam file DOCX.
 *
 * Dipakai bersama oleh validator, preflight render, dan guard placeholder
 * agar logika pembacaan arsip Word hanya ada di satu tempat.
 */
class WordDocumentTextExtractor
{
    /**
     * @throws RuntimeException bila file tidak dapat dibuka sebagai arsip ZIP.
     */
    public static function extract(string $path, string $openErrorMessage = 'Dokumen DOCX tidak dapat dibuka.'): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException($openErrorMessage);
        }

        $content = '';
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name) || ! preg_match('#^word/(document|header\d*|footer\d*)\.xml$#', $name)) {
                    continue;
                }

                $xml = $zip->getFromIndex($index);
                if (is_string($xml)) {
                    // Strip XML tags so macros split across Word runs can still be detected.
                    $content .= ' '.html_entity_decode((string) preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        } finally {
            $zip->close();
        }

        return $content;
    }
}
