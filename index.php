<?php

use Kirby\Filesystem\F;
use Lemmon\Extensions\FieldExtensions;
use Lemmon\Extensions\PageExtensions;

// Register autoloader for plugin classes
F::loadClasses([
    'Lemmon\Extensions\FieldExtensions' => 'src/FieldExtensions.php',
    'Lemmon\Extensions\PageExtensions' => 'src/PageExtensions.php',
], __DIR__);

/**
 * Extensions Plugin for Kirby CMS
 *
 * Common extensions providing additional page and field methods.
 * See README.md for details.
 *
 * @revision 2025-12-30 - Initial implementation with urlExtended() and excerptHtml() methods
 * @revision 2026-01-30 - Added related() method for finding related pages by field values with caching
 *
 * @see README.md
 */

Kirby::plugin('lemmon/extensions', [
    'options' => [
        /**
         * Cache configuration for the related pages cache.
         *
         * NOTE: Even though the cache is accessed as 'lemmon.extensions.related' via
         * kirby()->cache('lemmon.extensions.related'), the config key here is 'cache.related'.
         * This is correct - Kirby maps plugin cache configs using the 'cache.' prefix
         * followed by a simplified name. The full cache name 'lemmon.extensions.related' is
         * resolved automatically based on the plugin name 'lemmon/extensions'.
         *
         * The prefix is set to skip the hostname part so the cache works consistently
         * in both CLI and HTTP contexts (CLI uses '_' as hostname, causing path mismatch).
         */
        'cache.related' => [
            'active' => true,
            'type'   => 'file',
            'prefix' => 'lemmon/extensions/related', // Skip hostname prefix for CLI/HTTP consistency
        ],
        'relatedCacheExpiry' => PageExtensions::DEFAULT_RELATED_CACHE_EXPIRY, // 24 hours in minutes
    ],
    'pageMethods' => [
        /**
         * Extended URL method that adds content type representation support.
         * Supports both params (path-based, Kirby-style) and query (query string).
         * Both can be provided simultaneously and will be used together.
         *
         * @param array $options Options array (must be array for extended functionality)
         * @return string The URL with optional type extension
         */
        'urlExtended' => fn (array $options = []) => PageExtensions::urlExtended($this, $options),

        /**
         * Find related pages based on matching field values.
         * Returns pages sharing at least one field value, shuffled and limited.
         * Fills remaining slots with unrelated pages if needed.
         *
         * @param string $field Field name to match against (e.g., 'tags')
         * @param int $limit Maximum number of related pages to return
         * @param int|null $level Number of field values to consider for matching (e.g., 2 for first 2 tags). If null, uses all values.
         * @param \Kirby\Cms\Pages|null $pool Collection of pages to search within. Defaults to listed siblings of current page.
         * @return \Kirby\Cms\Pages Collection of related pages
         */
        'related' => fn (string $field, int $limit, ?int $level = null, ?object $pool = null) => PageExtensions::related($this, $field, $limit, $level, $pool),
    ],
    'fieldMethods' => [
        /**
         * Creates an HTML-preserving excerpt of the field value.
         *
         * Unlike Kirby's built-in excerpt() method, this preserves HTML tags
         * and adds ellipsis without a preceding space.
         *
         * @param int|null $length Maximum length in characters (text content only)
         * @return \Kirby\Cms\Field The field instance with modified value
         */
        'excerptHtml' => fn (?int $length = null) => FieldExtensions::excerptHtml($this, $length),
    ],
]);
