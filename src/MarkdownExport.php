<?php

namespace Lemmon\Extensions;

use Kirby\Data\Yaml;

/**
 * Markdown export helpers for front matter and heading normalization.
 */
class MarkdownExport
{
    /**
     * Build YAML front matter from metadata.
     */
    public static function toYamlFrontMatter(array $metadata): string
    {
        return "---\n" . rtrim(Yaml::encode($metadata)) . "\n---";
    }

    /**
     * Normalize article body headings for individual export semantics.
     * Converts body H1 headings to H2 and ensures body sections start at H2.
     */
    public static function normalizeArticleBody(string $markdown): string
    {
        $normalized = self::transformHeadings(
            $markdown,
            static fn (int $level): int => $level === 1 ? 2 : $level
        );

        $minimumLevel = self::minimumHeadingLevel($normalized);
        if ($minimumLevel === null || $minimumLevel <= 2) {
            return $normalized;
        }

        $liftBy = $minimumLevel - 2;

        return self::transformHeadings(
            $normalized,
            static fn (int $level): int => max(2, $level - $liftBy)
        );
    }

    /**
     * Shift body heading levels by the given number, clamped to H6.
     */
    public static function shiftHeadingLevels(string $markdown, int $levels = 1): string
    {
        if ($levels <= 0) {
            return $markdown;
        }

        return self::transformHeadings(
            $markdown,
            static fn (int $level): int => min(6, $level + $levels)
        );
    }

    /**
     * Keep body content as-is while ensuring it ends with a newline.
     */
    public static function ensureTrailingNewline(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }

        if (preg_match('/\R\z/u', $markdown) === 1) {
            return $markdown;
        }

        return $markdown . "\n";
    }

    /**
     * Resolve a stable unique id from page content, with page id fallback.
     *
     * @param \Kirby\Cms\Page $page
     */
    public static function stableId($page): string
    {
        $uuid = trim((string) $page->content()->get('uuid')->value());

        if ($uuid !== '') {
            return $uuid;
        }

        return $page->id();
    }

    /**
     * Transform heading lines while ignoring fenced code blocks.
     */
    private static function transformHeadings(string $markdown, callable $levelTransformer): string
    {
        if ($markdown === '') {
            return '';
        }

        $parts = preg_split('/(\r\n|\n|\r)/', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $markdown;
        }

        $insideFence = false;
        $fenceChar = '';
        $fenceLength = 0;

        for ($index = 0; $index < count($parts); $index += 2) {
            $line = $parts[$index];

            if (preg_match('/^\s{0,3}([`~]{3,})/', $line, $fenceMatch) === 1) {
                $currentFence = $fenceMatch[1];
                $currentFenceChar = $currentFence[0];
                $currentFenceLength = strlen($currentFence);

                if ($insideFence === false) {
                    $insideFence = true;
                    $fenceChar = $currentFenceChar;
                    $fenceLength = $currentFenceLength;
                } elseif ($currentFenceChar === $fenceChar && $currentFenceLength >= $fenceLength) {
                    $insideFence = false;
                    $fenceChar = '';
                    $fenceLength = 0;
                }

                continue;
            }

            if ($insideFence === true) {
                continue;
            }

            if (preg_match('/^(#{1,6})(\s+.*)$/', $line, $headingMatch) === 1) {
                $currentLevel = strlen($headingMatch[1]);
                $newLevel = (int) $levelTransformer($currentLevel);
                $newLevel = max(1, min(6, $newLevel));

                if ($newLevel !== $currentLevel) {
                    $parts[$index] = str_repeat('#', $newLevel) . $headingMatch[2];
                }
            }
        }

        return implode('', $parts);
    }

    /**
     * Find the smallest heading level outside fenced code blocks.
     */
    private static function minimumHeadingLevel(string $markdown): ?int
    {
        if ($markdown === '') {
            return null;
        }

        $parts = preg_split('/(\r\n|\n|\r)/', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return null;
        }

        $insideFence = false;
        $fenceChar = '';
        $fenceLength = 0;
        $minimum = null;

        for ($index = 0; $index < count($parts); $index += 2) {
            $line = $parts[$index];

            if (preg_match('/^\s{0,3}([`~]{3,})/', $line, $fenceMatch) === 1) {
                $currentFence = $fenceMatch[1];
                $currentFenceChar = $currentFence[0];
                $currentFenceLength = strlen($currentFence);

                if ($insideFence === false) {
                    $insideFence = true;
                    $fenceChar = $currentFenceChar;
                    $fenceLength = $currentFenceLength;
                } elseif ($currentFenceChar === $fenceChar && $currentFenceLength >= $fenceLength) {
                    $insideFence = false;
                    $fenceChar = '';
                    $fenceLength = 0;
                }

                continue;
            }

            if ($insideFence === true) {
                continue;
            }

            if (preg_match('/^(#{1,6})\s+/', $line, $headingMatch) === 1) {
                $level = strlen($headingMatch[1]);
                $minimum = $minimum === null ? $level : min($minimum, $level);
            }
        }

        return $minimum;
    }

}
