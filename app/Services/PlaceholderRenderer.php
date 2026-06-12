<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Recursively resolves [[Placeholder Name]] tokens in placeholder value strings.
 *
 * Placeholder keys in the input map are plain names without brackets, matching
 * PHPWord macro names (e.g. "Date in words", "Jurat", "Endorsement").
 */
class PlaceholderRenderer
{
    private const PLACEHOLDER_PATTERN = '/\[\[([^\[\]]+)\]\]/';

    private readonly int $maxPasses;

    public function __construct(?int $maxPasses = null)
    {
        $this->maxPasses = $maxPasses ?? (int) config('certificates.placeholder_renderer.max_passes', 5);
    }

    /**
     * @param  array<string, string>  $placeholders
     * @return array<string, string>
     */
    public function render(array $placeholders): array
    {
        $resolved = $placeholders;

        for ($pass = 1; $pass <= $this->maxPasses; $pass++) {
            $changed = false;

            foreach ($resolved as $key => $value) {
                $nextValue = $this->replacePlaceholdersInString($value, $resolved, (string) $key);

                if ($nextValue !== $value) {
                    $resolved[$key] = $nextValue;
                    $changed = true;
                }
            }

            if (! $changed || ! $this->containsUnresolvedPlaceholder($resolved)) {
                break;
            }
        }

        return $this->finalizeUnresolvedPlaceholders($resolved);
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function containsUnresolvedPlaceholder(array $placeholders): bool
    {
        foreach ($placeholders as $value) {
            if (preg_match(self::PLACEHOLDER_PATTERN, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function replacePlaceholdersInString(string $value, array $placeholders, string $ownerKey): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            function (array $matches) use ($placeholders, $ownerKey): string {
                $name = trim($matches[1]);

                if ($name === $ownerKey) {
                    Log::warning("PlaceholderRenderer: circular reference detected for [[{$name}]].");

                    return '';
                }

                if (! array_key_exists($name, $placeholders)) {
                    Log::warning("PlaceholderRenderer: missing placeholder [[{$name}]].");

                    return '';
                }

                $replacement = $placeholders[$name];

                if (preg_match(self::PLACEHOLDER_PATTERN, $replacement)
                    && str_contains($replacement, "[[{$ownerKey}]]")) {
                    Log::warning("PlaceholderRenderer: circular reference detected between [[{$ownerKey}]] and [[{$name}]].");

                    return '';
                }

                return $replacement;
            },
            $value,
        );
    }

    /**
     * @param  array<string, string>  $placeholders
     * @return array<string, string>
     */
    private function finalizeUnresolvedPlaceholders(array $placeholders): array
    {
        foreach ($placeholders as $key => $value) {
            if (! preg_match(self::PLACEHOLDER_PATTERN, $value)) {
                continue;
            }

            Log::warning("PlaceholderRenderer: unresolved placeholders remain in [[{$key}]] after {$this->maxPasses} passes.");

            $placeholders[$key] = (string) preg_replace(self::PLACEHOLDER_PATTERN, '', $value);
        }

        return $placeholders;
    }
}
