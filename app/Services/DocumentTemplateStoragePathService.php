<?php

namespace App\Services;

use App\Support\ActiveSpjContext;
use Throwable;

final class DocumentTemplateStoragePathService
{
    public function __construct(private readonly ActiveSpjContext $context) {}

    public function directory(int $fiscalYearId, string $suffix = ''): string
    {
        $directory = 'document-templates/'.$this->schoolKey().'/'.$fiscalYearId;

        if ($suffix === '') {
            return $directory;
        }

        $clean = trim($suffix, '/\\');
        foreach (preg_split('#[/\\\\]#', $clean) ?: [] as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Suffix folder template dokumen tidak valid.');
            }
        }

        return $directory.'/'.$clean;
    }

    private function schoolKey(): string
    {
        try {
            $school = $this->context->school();
            $candidate = trim((string) ($school->npsn ?: $school->school_code ?: $school->id));
        } catch (Throwable) {
            $candidate = 'school-'.$this->context->schoolId();
        }

        $key = preg_replace('/[^A-Za-z0-9_-]+/', '-', $candidate) ?: 'school-'.$this->context->schoolId();

        return trim($key, '-_') ?: 'school-'.$this->context->schoolId();
    }
}
