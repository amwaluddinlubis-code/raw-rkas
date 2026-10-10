<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationIndexConsistencyTest extends TestCase
{
    public function test_mobile_visual_qa_checklist_has_one_canonical_location(): void
    {
        $legacyChecklist = base_path('docs/MOBILE_VISUAL_QA_TODO.md');
        $canonicalChecklist = base_path('docs/GUI_RUNTIME_QA.md');
        $documentationIndex = base_path('docs/README.md');

        $this->assertFileDoesNotExist($legacyChecklist);
        $this->assertFileExists($canonicalChecklist);
        $this->assertFileExists($documentationIndex);

        $canonicalContent = file_get_contents($canonicalChecklist);
        $indexContent = file_get_contents($documentationIndex);

        $this->assertIsString($canonicalContent);
        $this->assertIsString($indexContent);
        $this->assertStringContainsString(
            'Checklist tambahan mobile (digabung dari MOBILE_VISUAL_QA_TODO.md, 2026-10-11)',
            $canonicalContent,
        );
        $this->assertStringContainsString('GUI_RUNTIME_QA.md', $indexContent);
    }
}
