<?php

namespace Lemmon\Extensions;

use Kirby\Http\Params;
use Kirby\Http\Query;

/**
 * Extended page methods for Kirby CMS
 */
class PageExtensions
{
    /**
     * Default cache expiry for related pages (24 hours in minutes)
     */
    public const DEFAULT_RELATED_CACHE_EXPIRY = 1440;
    /**
     * Extended URL method that adds content type representation support.
     * Supports both params (path-based, Kirby-style) and query (query string).
     * Both can be provided simultaneously and will be used together.
     *
     * @param \Kirby\Cms\Page $page The page instance
     * @param array $options Options array (must be array for extended functionality)
     * @return string The URL with optional type extension
     */
    public static function urlExtended($page, array $options = []): string
    {
        // Extract extended parameters from options
        $type = $options['type'] ?? null;
        $query = $options['query'] ?? null;
        $params = $options['params'] ?? null;
        $fragment = $options['fragment'] ?? null;

        // Remove extended parameters from options
        unset($options['type'], $options['query'], $options['params'], $options['fragment']);

        // Get base URL from Kirby's url() method (without params, query, fragment)
        $baseUrl = $page->url($options);
        $extendedPath = '';

        // Homepage edge case: homepage URL is typically '/' or empty
        // If we need to add type/params, we need a path to extend
        if ($page->isHomePage() && ($type !== null || $params !== null)) {
            $slug = $page->slug();
            // Only add slug if it exists and is not empty
            if ($slug !== '' && $slug !== '/') {
                $extendedPath = '/' . $slug;
            } else {
                // For homepage with empty slug, use root path
                $extendedPath = '/';
            }
        }

        // Append type extension to path BEFORE params (e.g., /snippets.md or /.md for homepage)
        if ($type !== null && $type !== '') {
            $extendedPath .= '.' . $type;
        }

        // Normalize and format params for path (Kirby-style: /key:value)
        if ($params !== null) {
            // Convert to Params object if needed
            if (is_object($params) && $params instanceof Params) {
                $paramsObj = $params;
            } elseif (is_object($params) && method_exists($params, 'toArray')) {
                $paramsObj = new Params($params->toArray());
            } elseif (is_array($params)) {
                $paramsObj = new Params($params);
            } else {
                $paramsObj = new Params((array) $params);
            }

            // Format params as path string (e.g., /tag:CSS) and append to path
            // Params::toString(true) includes leading slash, so it appends correctly
            if ($paramsObj->isNotEmpty()) {
                $extendedPath .= $paramsObj->toString(true);
            }
        }

        // Normalize and format query for query string
        $queryString = '';
        if ($query !== null) {
            // Convert to Query object if needed
            if (is_object($query) && $query instanceof Query) {
                $queryObj = $query;
            } elseif (is_object($query) && method_exists($query, 'toArray')) {
                $queryObj = new Query($query->toArray());
            } elseif (is_array($query)) {
                $queryObj = new Query($query);
            } else {
                $queryObj = new Query((array) $query);
            }

            // Format query as query string (e.g., ?tag=CSS)
            if ($queryObj->isNotEmpty()) {
                $queryString = $queryObj->toString(true);
            }
        }

        // Handle fragment
        $fragmentString = '';
        if ($fragment !== null) {
            $fragmentString = '#' . ltrim($fragment, '#');
        }

        // Reconstruct URL: base + extended path + query + fragment
        // Normalize to avoid double slashes (e.g., if $baseUrl ends with '/' and $extendedPath starts with '/')
        if ($extendedPath !== '' && str_ends_with($baseUrl, '/') && str_starts_with($extendedPath, '/')) {
            $baseUrl = rtrim($baseUrl, '/');
        }

        return $baseUrl . $extendedPath . $queryString . $fragmentString;
    }

    /**
     * Find related pages based on matching field values.
     * Returns pages sharing at least one field value, shuffled and limited.
     * Fills remaining slots with unrelated pages if needed.
     * Results are cached for 24 hours for improved performance.
     *
     * @param \Kirby\Cms\Page $page The page instance
     * @param string $field Field name to match against (e.g., 'tags')
     * @param int $limit Maximum number of related pages to return
     * @param int|null $level Number of field values to consider for matching (e.g., 2 for first 2 tags). If null, uses all values.
     * @param \Kirby\Cms\Pages|null $pool Collection of pages to search within. Defaults to listed siblings of current page.
     * @return \Kirby\Cms\Pages Collection of related pages
     */
    public static function related($page, string $field, int $limit, ?int $level = null, ?object $pool = null)
    {
        // Create cache key based on page ID, field, limit, level, and pool identifier
        $poolId = $pool !== null ? 'custom' : 'siblings';
        $cacheKey = self::createRelatedCacheKey($page->id(), $field, $limit, $level, $poolId);

        // Check cache first
        $cache = kirby()->cache('lemmon.extensions.related');
        $cachedPageIds = $cache->get($cacheKey);

        if ($cachedPageIds !== null && is_array($cachedPageIds)) {
            // Reconstruct collection from cached page IDs, preserving order
            $pages = [];
            foreach ($cachedPageIds as $pageId) {
                $cachedPage = $page->site()->find($pageId);
                if ($cachedPage !== null) {
                    $pages[] = $cachedPage;
                }
            }

            // Verify all pages still exist and are accessible
            if (count($pages) === count($cachedPageIds)) {
                return new \Kirby\Cms\Pages($pages);
            }
        }

        // Get pool of pages to search (default to listed siblings excluding current page)
        $pool = ($pool ?? $page->siblings()->listed())->not($page);

        // Get current page's field values
        $currentValues = $page->$field()->split();

        // Limit to first N values if level is specified
        if ($level !== null && $level > 0) {
            $currentValues = array_slice($currentValues, 0, $level);
        }

        // Start with empty collection
        $result = $pool->slice(0, 0);

        // Find related pages if there are values to match
        if (count($currentValues) > 0) {
            $relatedPages = $pool->filter(function ($item) use ($field, $currentValues) {
                $itemField = $item->$field();
                if ($itemField->isEmpty()) {
                    return false;
                }
                $itemValues = $itemField->split();
                return count(array_intersect($currentValues, $itemValues)) > 0;
            })->shuffle()->limit($limit);

            $result = $result->merge($relatedPages);
        }

        // Fill remaining slots with unrelated pages if needed
        if ($result->count() < $limit) {
            $unrelatedPages = $pool->not($result)->shuffle()->limit($limit - $result->count());
            $result = $result->merge($unrelatedPages);
        }

        // Cache page IDs for 24 hours
        $pageIds = [];
        foreach ($result as $item) {
            $pageIds[] = $item->id();
        }
        $cacheExpiry = kirby()->option('lemmon.extensions.relatedCacheExpiry', self::DEFAULT_RELATED_CACHE_EXPIRY);
        $cache->set($cacheKey, $pageIds, $cacheExpiry);

        return $result;
    }

    /**
     * Create a cache key for related pages lookup
     *
     * @param string $pageId The page ID
     * @param string $field Field name
     * @param int $limit Limit value
     * @param int|null $level Level value
     * @param string $poolId Pool identifier
     * @return string Cache key
     */
    private static function createRelatedCacheKey(string $pageId, string $field, int $limit, ?int $level, string $poolId): string
    {
        $content = $pageId . '|' . $field . '|' . $limit . '|' . ($level ?? 'all') . '|' . $poolId;
        return 'related_' . hash('sha256', $content);
    }
}
