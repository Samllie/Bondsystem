<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Normalizes DOCX templates by merging split-run placeholders.
 *
 * Word processors like Microsoft Word sometimes split placeholder text (e.g.,
 * [[Name]]) across multiple <w:r> XML elements due to spell-check, autocorrect,
 * or revision tracking. This service reads word/document.xml from a DOCX file,
 * reconstructs contiguous placeholder tokens from fragmented runs within each
 * paragraph, and re-packages the file so that PHPWord TemplateProcessor can
 * reliably replace every [[placeholder]].
 */
class TemplateNormalizerService
{
    /**
     * Normalize a DOCX template file and return the path to the fixed copy.
     *
     * The original file is never modified. A copy is written to the system's
     * temp directory and that path is returned.
     *
     * @throws RuntimeException when the file cannot be read or written.
     */
    public function normalize(string $templatePath): string
    {
        if (! file_exists($templatePath)) {
            throw new RuntimeException("Template not found: {$templatePath}");
        }

        $normalizedPath = $this->buildTempPath($templatePath);

        if (! copy($templatePath, $normalizedPath)) {
            throw new RuntimeException("Could not copy template to temp path: {$normalizedPath}");
        }

        $zip = new ZipArchive;

        if ($zip->open($normalizedPath) !== true) {
            throw new RuntimeException("Could not open DOCX archive: {$normalizedPath}");
        }

        $xmlFiles = ['word/document.xml', 'word/header1.xml', 'word/footer1.xml'];

        foreach ($xmlFiles as $xmlFile) {
            $content = $zip->getFromName($xmlFile);

            if ($content === false) {
                continue;
            }

            $normalized = $this->normalizeSplitRuns($content);
            $normalized = $this->removeTrailingPeriodAfterYearPlaceholder($normalized);
            $zip->addFromString($xmlFile, $normalized);
        }

        $zip->close();

        return $normalizedPath;
    }

    /**
     * Merge fragmented text runs that together form a [[placeholder]] token,
     * then convert every [[placeholder]] to ${placeholder} so that
     * PHPWord TemplateProcessor::setValue() can find it.
     *
     * The algorithm walks every <w:r> run in document order (regardless of how
     * paragraphs are nested — e.g. inside VML textboxes). When a run opens a
     * placeholder ('[[') that is not closed within the same run, it accumulates
     * the text of following runs until the placeholder is closed, then emits a
     * single merged run carrying the opening run's properties (<w:rPr>). Merging
     * never crosses a paragraph boundary (</w:p>) because placeholders never
     * span paragraphs.
     */
    private function normalizeSplitRuns(string $xml): string
    {
        if (! preg_match_all('/<w:r\b[^>]*>.*?<\/w:r>/s', $xml, $matches, PREG_OFFSET_CAPTURE)) {
            return $this->convertBrackets($xml);
        }

        $runs = $matches[0];
        $total = count($runs);
        $out = '';
        $cursor = 0;
        $i = 0;

        while ($i < $total) {
            [$runXml, $offset] = $runs[$i];

            // Emit any markup that precedes this run unchanged.
            $out .= substr($xml, $cursor, $offset - $cursor);

            $text = $this->extractRunText($runXml);

            // A run is left untouched unless it opens a placeholder ('[[') that is
            // not closed within the same run. A run may legitimately contain
            // complete placeholders AND a trailing unterminated '[[', so we look
            // for an open bracket that has no ']]' after it — not just any ']]'.
            if (! $this->hasUnterminatedOpen($text)) {
                $out .= $runXml;
                $cursor = $offset + strlen($runXml);
                $i++;

                continue;
            }

            $rprXml = $this->extractRpr($runXml);
            $buffer = $text;
            $endOffset = $offset + strlen($runXml);
            $j = $i + 1;
            $closed = false;

            while ($j < $total) {
                [$nextRun, $nextOffset] = $runs[$j];

                // Never merge across a paragraph boundary.
                $between = substr($xml, $endOffset, $nextOffset - $endOffset);
                if (str_contains($between, '</w:p>')) {
                    break;
                }

                $buffer .= $this->extractRunText($nextRun);
                $endOffset = $nextOffset + strlen($nextRun);
                $j++;

                if (! $this->hasUnterminatedOpen($buffer)) {
                    $closed = true;
                    break;
                }
            }

            if ($closed) {
                $out .= $this->buildMergedRun($rprXml, $buffer);
                $cursor = $endOffset;
                $i = $j;
            } else {
                // Placeholder never closes within this paragraph — leave as-is.
                $out .= $runXml;
                $cursor = $offset + strlen($runXml);
                $i++;
            }
        }

        $out .= substr($xml, $cursor);

        return $this->convertBrackets($out);
    }

    /**
     * Convert [[Foo]] tokens to ${Foo} so TemplateProcessor::setValue() can find
     * them. The inner match excludes '<' and '>' so only placeholders contained
     * within a single text node are converted; this prevents an unmerged token
     * (split across runs/paragraphs) from being turned into a malformed macro
     * that spans XML tags.
     */
    private function convertBrackets(string $xml): string
    {
        return (string) preg_replace('/\[\[([^\[\]<>]+)\]\]/', '${$1}', $xml);
    }

    /**
     * CAR templates place a literal period immediately after the Year placeholder
     * (e.g. [[Year]].). Remove it so series-year wording does not end with a period.
     */
    private function removeTrailingPeriodAfterYearPlaceholder(string $xml): string
    {
        return str_replace(['[[Year]].', '${Year}.'], ['[[Year]]', '${Year}'], $xml);
    }

    /**
     * Determine whether the text contains a '[[' that is not yet closed by a
     * later ']]'. This correctly ignores complete placeholders that appear
     * earlier in the same run.
     */
    private function hasUnterminatedOpen(string $text): bool
    {
        $lastOpen = strrpos($text, '[[');

        if ($lastOpen === false) {
            return false;
        }

        $lastClose = strrpos($text, ']]');

        return $lastClose === false || $lastClose < $lastOpen;
    }

    private function extractRunText(string $runXml): string
    {
        // Collect all <w:t> text nodes within this run.
        preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $runXml, $matches);

        return implode('', $matches[1] ?? []);
    }

    private function extractRpr(string $runXml): string
    {
        if (preg_match('/<w:rPr>.*?<\/w:rPr>/s', $runXml, $match)) {
            return $match[0];
        }

        return '';
    }

    private function buildMergedRun(string $rprXml, string $text): string
    {
        $space = str_contains($text, ' ') ? ' xml:space="preserve"' : '';

        return '<w:r>'
            .$rprXml
            ."<w:t{$space}>{$this->escapeXml($text)}</w:t>"
            .'</w:r>';
    }

    private function escapeXml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function buildTempPath(string $sourcePath): string
    {
        $hash = substr(md5($sourcePath.microtime()), 0, 8);

        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'normalized_'.$hash.'.docx';
    }
}
