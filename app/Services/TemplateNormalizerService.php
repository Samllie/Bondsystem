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
     * The algorithm walks each <w:p> (paragraph) element and concatenates the
     * text of consecutive <w:r> elements. When the running buffer matches
     * /\[\[.+?\]\]/ it replaces those runs with a single run that carries the
     * first run's properties (<w:rPr>) and the merged text. Runs that do not
     * participate in any placeholder are left unchanged.
     */
    private function normalizeSplitRuns(string $xml): string
    {
        // Step 1: merge runs per paragraph.
        $merged = preg_replace_callback(
            '/<w:p[ >].*?<\/w:p>/s',
            fn (array $match) => $this->normalizeParagraph($match[0]),
            $xml,
        ) ?? $xml;

        // Step 2: convert [[Foo]] → ${Foo} so TemplateProcessor can find them.
        return (string) preg_replace('/\[\[([^\[\]]+)\]\]/', '${$1}', $merged);
    }

    private function normalizeParagraph(string $paragraphXml): string
    {
        // Extract all <w:r>…</w:r> blocks (runs) from the paragraph.
        if (! preg_match_all('/<w:r[ >].*?<\/w:r>/s', $paragraphXml, $runMatches)) {
            return $paragraphXml;
        }

        $runs = $runMatches[0];
        $texts = array_map([$this, 'extractRunText'], $runs);
        $fullText = implode('', $texts);

        // If there are no placeholder fragments in this paragraph, skip it.
        if (! str_contains($fullText, '[[') && ! str_contains($fullText, ']]')) {
            return $paragraphXml;
        }

        $mergedRuns = $this->mergeRunsForPlaceholders($runs, $texts);

        // Rebuild the paragraph by replacing the original run sequence with the
        // merged one. We replace the entire block of consecutive runs in one
        // shot to avoid mis-matched replacements.
        $firstRun = $runs[0];
        $lastRun = $runs[count($runs) - 1];

        // Find the span from the first run to the last run in the paragraph XML.
        $startPos = strpos($paragraphXml, $firstRun);
        $endPos = strrpos($paragraphXml, $lastRun);

        if ($startPos === false || $endPos === false) {
            return $paragraphXml;
        }

        $endPos += strlen($lastRun);
        $before = substr($paragraphXml, 0, $startPos);
        $after = substr($paragraphXml, $endPos);

        return $before.implode('', $mergedRuns).$after;
    }

    /**
     * @param  array<int, string>  $runs
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    private function mergeRunsForPlaceholders(array $runs, array $texts): array
    {
        $result = [];
        $i = 0;
        $total = count($runs);

        while ($i < $total) {
            $buffer = $texts[$i];

            // No placeholder start here — emit the run as-is and move on.
            if (! str_contains($buffer, '[[') || str_contains($buffer, '[[') && str_contains($buffer, ']]')) {
                // If we already have a complete placeholder in a single run, just emit it.
                $result[] = $this->rebuildRun($runs[$i], $buffer);
                $i++;

                continue;
            }

            // We have a '[[ ' that doesn't yet have ']]' — start accumulating.
            $groupStart = $i;
            $rprXml = $this->extractRpr($runs[$i]);
            $j = $i + 1;

            while ($j < $total && ! str_contains($buffer, ']]')) {
                $buffer .= $texts[$j];
                $j++;
            }

            // Build a single merged run for the whole placeholder group.
            $result[] = $this->buildMergedRun($rprXml, $buffer);

            // Any runs that were consumed but don't need their original form
            // are already absorbed into $buffer — skip them.
            $i = $j;
        }

        return $result;
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

    private function rebuildRun(string $originalRun, string $text): string
    {
        // Replace the text inside <w:t> nodes with the (possibly unchanged) text.
        // This handles xml:space="preserve" correctly.
        return preg_replace_callback(
            '/<w:t([^>]*)>.*?<\/w:t>/s',
            function (array $m) use ($text): string {
                $attrs = $m[1];
                if (str_contains($text, ' ') && ! str_contains($attrs, 'xml:space')) {
                    $attrs .= ' xml:space="preserve"';
                }

                return "<w:t{$attrs}>{$this->escapeXml($text)}</w:t>";
            },
            $originalRun,
            1,
        ) ?? $originalRun;
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
