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
 * @see README.md
 */

Kirby::plugin('lemmon/extensions', [
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
