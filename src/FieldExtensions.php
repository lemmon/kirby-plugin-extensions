<?php

namespace Lemmon\Extensions;

/**
 * Extended field methods for Kirby CMS
 */
class FieldExtensions
{
    /**
     * Creates an HTML-preserving excerpt of the field value.
     *
     * Unlike Kirby's built-in `excerpt()` method, this method:
     * - Preserves HTML tags in the output
     * - Adds ellipsis without a preceding space (uses &hellip; directly)
     * - Processes content with kirbytextinline for inline Kirbytext support
     * - Closes any unclosed HTML tags to ensure valid HTML output
     *
     * The method takes the first line of content, processes it as inline Kirbytext,
     * then truncates to the specified length while preserving word boundaries
     * and HTML structure.
     *
     * @param \Kirby\Cms\Field $field The field instance
     * @param int|null $length Maximum length in characters (text content only, HTML tags excluded). If null, no truncation is performed.
     * @return \Kirby\Cms\Field The field instance with modified value
     */
    public static function excerptHtml($field, ?int $length = null)
    {
        $value = $field->value;

        // Get first line only
        $value = explode("\n", $value)[0];

        // Process inline Kirbytext (supports inline tags like (link: url text: Link Text))
        $value = kirbytextinline($value);

        // Truncate to length while preserving HTML tags and word boundaries
        if ($length !== null && $length > 0) {
            while (strlen(strip_tags($value)) > $length) {
                // Remove trailing word characters and punctuation, preserving HTML tags
                // This regex removes: non-word chars (except > and .), then word chars, then non-word chars
                // The '>' is preserved to maintain HTML tag boundaries
                $value = preg_replace('/[^\w>\.]*\w+\W*$/u', '', $value);
            }
        }

        // Ensure all HTML tags are properly closed
        $value = self::closeHtmlTags($value);

        // Remove any trailing empty HTML tags (e.g., <span></span>)
        $value = self::removeTrailingEmptyHtmlTag($value);

        // Clean up any trailing non-word characters (except HTML entities and tags)
        $value = preg_replace('/[^\w>\.]*$/u', '', $value);

        // Add ellipsis if truncated and doesn't end with a period
        if ($length !== null && $length > 0 && substr($value, -1) !== '.') {
            $value .= '&hellip;';
        }

        $field->value = $value;

        return $field;
    }

    /**
     * Closes any unclosed HTML tags in the given HTML string.
     *
     * Analyzes open and close tags, identifies missing closing tags,
     * and appends them in reverse order to maintain proper nesting.
     * Self-closing tags (e.g., <br>, <img>) are ignored.
     *
     * @param string $html The HTML string to process
     * @return string HTML string with all tags properly closed
     */
    private static function closeHtmlTags(string $html): string
    {
        // List of HTML5 void/self-closing elements that don't require closing tags
        $singleTags = [
            'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
            'link', 'meta', 'param', 'source', 'track', 'wbr',
        ];

        // Match opening tags (including attributes, but excluding self-closing syntax)
        preg_match_all('/<([a-z0-9]+)(?:\s+[^>]*)?(?<!\/)>/i', $html, $openTags);
        // Match closing tags
        preg_match_all('/<\/([a-z0-9]+)>/i', $html, $closeTags);

        $openTags = $openTags[1] ?? [];
        $closeTags = $closeTags[1] ?? [];

        // Count occurrences of each tag
        $openCounts = array_count_values($openTags);
        $closeCounts = array_count_values($closeTags);

        // Find tags that are opened but not closed
        $missingTags = [];
        foreach ($openCounts as $tag => $count) {
            if (!in_array(strtolower($tag), $singleTags, true)) {
                $diff = $count - ($closeCounts[$tag] ?? 0);
                if ($diff > 0) {
                    // Add the missing closing tags
                    $missingTags = array_merge($missingTags, array_fill(0, $diff, $tag));
                }
            }
        }

        // Close tags in reverse order to maintain proper nesting
        foreach (array_reverse($missingTags) as $tag) {
            $html .= "</$tag>";
        }

        return $html;
    }

    /**
     * Removes trailing empty HTML tags from the string.
     *
     * Matches patterns like <tag></tag> or <tag attributes></tag>
     * that appear at the end of the string (with optional trailing whitespace).
     *
     * @param string $html The HTML string to process
     * @return string HTML string with trailing empty tags removed
     */
    private static function removeTrailingEmptyHtmlTag(string $html): string
    {
        // Match empty tags at the end: <tag> or <tag attrs> followed by </tag> and optional whitespace
        return preg_replace('/<([a-z0-9]+)(?:\s+[^>]*)?>\s*<\/\1>\s*$/i', '', $html);
    }
}
