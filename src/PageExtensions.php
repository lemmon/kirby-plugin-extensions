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
}
