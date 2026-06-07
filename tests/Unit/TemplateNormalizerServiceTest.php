<?php

namespace Tests\Unit;

use App\Services\TemplateNormalizerService;
use PHPUnit\Framework\TestCase;

class TemplateNormalizerServiceTest extends TestCase
{
    private TemplateNormalizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateNormalizerService;
    }

    public function test_normalize_returns_path_when_file_does_not_exist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template not found');

        $this->service->normalize('/nonexistent/template.docx');
    }

    public function test_normalize_merges_split_placeholder_runs(): void
    {
        // Simulate the XML of a paragraph where [[Obligee]] is split across runs.
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <root>
            <w:p>
            <w:r><w:t>[[</w:t></w:r>
            <w:r><w:rPr><w:b/></w:rPr><w:t>Obligee</w:t></w:r>
            <w:r><w:t>]]</w:t></w:r>
            </w:p>
            </root>
            XML;

        $method = new \ReflectionMethod(TemplateNormalizerService::class, 'normalizeSplitRuns');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $xml);

        $this->assertStringContainsString('${Obligee}', $result);
        $this->assertStringNotContainsString('[[', $result);
        $this->assertStringNotContainsString(']]', $result);
    }

    public function test_normalize_converts_bracket_placeholders_to_phpword_format(): void
    {
        $xml = '<root><w:p><w:r><w:t>[[Principal]]</w:t></w:r></w:p></root>';

        $method = new \ReflectionMethod(TemplateNormalizerService::class, 'normalizeSplitRuns');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $xml);

        $this->assertStringContainsString('${Principal}', $result);
        $this->assertStringNotContainsString('[[Principal]]', $result);
    }

    public function test_normalize_leaves_normal_text_unchanged(): void
    {
        $xml = '<root><w:p><w:r><w:t>Hello World</w:t></w:r></w:p></root>';

        $method = new \ReflectionMethod(TemplateNormalizerService::class, 'normalizeSplitRuns');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $xml);

        $this->assertStringContainsString('Hello World', $result);
    }

    public function test_normalize_handles_multiple_placeholders_in_paragraph(): void
    {
        $xml = <<<'XML'
            <root>
            <w:p>
            <w:r><w:t xml:space="preserve">Bond number: [[</w:t></w:r>
            <w:r><w:rPr><w:b/></w:rPr><w:t>Bond</w:t></w:r>
            <w:r><w:t>]] and obligee: [[Obligee]]</w:t></w:r>
            </w:p>
            </root>
            XML;

        $method = new \ReflectionMethod(TemplateNormalizerService::class, 'normalizeSplitRuns');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $xml);

        $this->assertStringContainsString('${Obligee}', $result);
    }
}
