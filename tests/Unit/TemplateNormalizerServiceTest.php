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

    public function test_normalize_merges_placeholders_inside_nested_textbox_paragraphs(): void
    {
        // Runs split as '[[' + 'Name]]' inside a VML textbox whose paragraphs are
        // nested within an outer paragraph. The merge must not depend on matching
        // outer/inner paragraph boundaries.
        $xml = <<<'XML'
            <root>
            <w:p><w:r><w:rPr><w:noProof/></w:rPr><w:drawing><v:textbox><w:txbxContent>
            <w:p>
            <w:r><w:rPr><w:b/></w:rPr><w:t>[[</w:t></w:r>
            <w:r><w:rPr><w:b/></w:rPr><w:t>Date in words]]</w:t></w:r>
            <w:r><w:t xml:space="preserve"> at </w:t></w:r>
            <w:r><w:rPr><w:b/></w:rPr><w:t>[[</w:t></w:r>
            <w:r><w:rPr><w:b/></w:rPr><w:t>Branch city]]</w:t></w:r>
            </w:p>
            </w:txbxContent></v:textbox></w:drawing></w:r></w:p>
            </root>
            XML;

        $method = new \ReflectionMethod(TemplateNormalizerService::class, 'normalizeSplitRuns');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $xml);

        $this->assertStringContainsString('${Date in words}', $result);
        $this->assertStringContainsString('${Branch city}', $result);
        $this->assertStringNotContainsString('[[', $result);
        $this->assertStringNotContainsString(']]', $result);
    }

    public function test_normalize_does_not_merge_across_paragraph_boundary(): void
    {
        // An unterminated '[[' at the end of one paragraph must not swallow text
        // from the next paragraph.
        $xml = <<<'XML'
            <root>
            <w:p><w:r><w:t>dangling [[</w:t></w:r></w:p>
            <w:p><w:r><w:t>Next paragraph]]</w:t></w:r></w:p>
            </root>
            XML;

        $method = new \ReflectionMethod(TemplateNormalizerService::class, 'normalizeSplitRuns');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $xml);

        // The two paragraphs stay separate; the broken token is left untouched.
        $this->assertStringContainsString('</w:p>', $result);
        $this->assertStringNotContainsString('${', $result);
    }

    public function test_normalize_merges_split_run_after_a_complete_placeholder_in_same_run(): void
    {
        // The opening run carries a COMPLETE placeholder ([[Branch]]) followed by an
        // unterminated open ([[) whose value continues in later runs. The merge must
        // not be fooled by the earlier ']]' into treating the run as complete.
        $xml = <<<'XML'
            <root>
            <w:p>
            <w:r><w:t xml:space="preserve">issued by [[Branch]] in the amount of PESOS [[</w:t></w:r>
            <w:r><w:rPr><w:b/></w:rPr><w:t>Amount in words</w:t></w:r>
            <w:r><w:t xml:space="preserve">]] only</w:t></w:r>
            </w:p>
            </root>
            XML;

        $method = new \ReflectionMethod(TemplateNormalizerService::class, 'normalizeSplitRuns');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $xml);

        $this->assertStringContainsString('${Branch}', $result);
        $this->assertStringContainsString('${Amount in words}', $result);
        $this->assertStringNotContainsString('[[', $result);
        $this->assertStringNotContainsString(']]', $result);
    }

    public function test_normalize_removes_trailing_period_after_year_placeholder(): void
    {
        $method = new \ReflectionMethod(TemplateNormalizerService::class, 'removeTrailingPeriodAfterYearPlaceholder');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->service,
            '<w:t>[[Year]].</w:t><w:t>${Year}.</w:t>',
        );

        $this->assertSame('<w:t>[[Year]]</w:t><w:t>${Year}</w:t>', $result);
    }
}
