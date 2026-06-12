<?php

namespace App\Services;

use ZipArchive;

/**
 * Cleans duplicate spacing left when [[Endorsement]] resolves to blank in templates
 * such as "[[Bond]] [[Endorsement]] issued".
 *
 * Does not globally collapse spaces across the document.
 */
class DocxEndorsementSpacingNormalizer
{
    /**
     * @var array<int, string>
     */
    private const XML_FILES = [
        'word/document.xml',
        'word/header1.xml',
        'word/footer1.xml',
    ];

    public function normalize(string $docxPath): void
    {
        if (! file_exists($docxPath)) {
            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($docxPath) !== true) {
            return;
        }

        foreach (self::XML_FILES as $xmlFile) {
            $content = $zip->getFromName($xmlFile);

            if ($content === false) {
                continue;
            }

            $normalized = $this->normalizeParagraphs($content);
            $zip->addFromString($xmlFile, $normalized);
        }

        $zip->close();
    }

    private function normalizeParagraphs(string $xml): string
    {
        return (string) preg_replace_callback(
            '/<w:p\b[^>]*>.*?<\/w:p>/s',
            function (array $matches): string {
                $paragraph = $matches[0];

                if (str_contains($paragraph, 'W/ENDT.NO')) {
                    return $paragraph;
                }

                $plainText = strip_tags(str_replace(['<w:tab/>', '<w:br/>'], [' ', ' '], $paragraph));

                if (! preg_match('/\s{2,}issued\b/i', $plainText)) {
                    return $paragraph;
                }

                return (string) preg_replace(
                    '/(\s)\s+(?=issued\b)/i',
                    '$1',
                    $paragraph,
                );
            },
            $xml,
        );
    }
}
