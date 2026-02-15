# Extensions

Common extensions for Kirby CMS providing additional page methods, field methods, and markdown export helpers.

## Install

```sh
git submodule add https://github.com/lemmon/kirby-plugin-extensions site/plugins/extensions
```

## Page Methods

- **`urlExtended()`** -- Extended URL with content type representation, params, and query support
- **`related()`** -- Find related pages by matching field values with caching

## Field Methods

- **`excerptHtml()`** -- HTML-preserving excerpt with ellipsis

## Helpers

- **`MarkdownExport`** -- YAML front matter generation and heading normalization for markdown exports
