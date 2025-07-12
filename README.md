# Optimus Bard v2.0

> Advanced search transformer for Statamic Bard fields with deep content extraction and Statamic 5 support

## ✨ What's New in v2.0

- **Statamic 5 Support**: Fully compatible with Statamic 5 and Laravel 11+
- **Deep Content Extraction**: Recursively extracts content from nested Bard fields and complex sets
- **Enhanced Set Processing**: Automatically detects and extracts content from all text-bearing fields within sets
- **Improved Error Handling**: Graceful fallbacks for malformed content or missing blueprints
- **Modern PHP**: Requires PHP 8.2+ with modern Laravel helpers

## Features

What this addon does:

- **Deep Content Extraction**: Processes not just top-level Bard text blocks, but also recursively extracts content from:
  - Nested Bard fields within sets
  - Text fields, textareas, and markdown fields within component sets
  - Title, description, and content fields in complex page builders
- **Smart Set Processing**: Automatically identifies content-bearing fields without requiring manual configuration
- **Backward Compatibility**: Maintains compatibility with existing v1.x configurations
- **Configurable Depth**: Prevents infinite recursion with configurable extraction depth limits
- **Enhanced Cleanup**: Improved text cleaning and normalization for better search results

## Requirements

- PHP ^8.2
- Statamic ^5.0
- Laravel ^11.0

## Installation

You can search for this addon in the `Tools > Addons` section of the Statamic control panel and click **install**, or run the following command from your project root:

```bash
composer require fitzage/optimus-bard
```

## Basic Usage

Configure your search indexes using [the Statamic search documentation](https://statamic.dev/search).

In `config/statamic/search.php`, add the following at the top of the file, below the `<?php` line:

```php
use Fitzage\OptimusBard\TransformBard;
```

### Simple Configuration

For basic Bard fields with standard content:

```php
'transformers' => [
    'content' => function ($content) {
        return TransformBard::transform($content, 'collections/pages/page', 'content');
    },
]
```

### Advanced Configuration

For complex page builders with nested content:

```php
'transformers' => [
    'blocks' => function ($blocks) {
        return TransformBard::transform(
            $blocks, 
            'collections/pages/pages', 
            'blocks',
            ['hero', 'features', 'testimonials'], // Additional set types to include
            [
                'max_depth' => 5,        // Maximum recursion depth
                'max_length' => 100000   // Maximum output length
            ]
        );
    },
]
```

## Configuration Options

### Parameters

1. **$bard** (required): The Bard field content
2. **$blueprintPath** (required): Path to the blueprint (e.g., `collections/pages/page`)
3. **$fieldName** (required): Name of the field in the blueprint
4. **$userSetTypes** (optional): Array of additional set types to specifically include
5. **$options** (optional): Configuration array with the following options:
   - `max_depth` (default: 3): Maximum recursion depth for nested content
   - `max_length` (default: 90000): Maximum length of the output string

### Automatic Content Detection

The transformer automatically detects and extracts content from:

- Standard Bard text blocks
- Nested Bard fields within sets
- Common content field types: `text`, `textarea`, `markdown`, `title`, `description`, `content`, `heading`, `body`
- Complex component structures (hero sections, testimonials, features, etc.)

## Complete Example

Here's a complete search configuration for a modern Statamic site with complex content structures:

```php
<?php

use Fitzage\OptimusBard\TransformBard;

return [
    'default' => env('STATAMIC_DEFAULT_SEARCH_INDEX', 'default'),
    
    'indexes' => [
        'pages' => [
            'driver' => 'algolia',
            'searchables' => 'collection:pages',
            'fields' => ['title', 'blocks', 'url'],
            'transformers' => [
                'blocks' => function ($blocks) {
                    return TransformBard::transform(
                        $blocks, 
                        'collections/pages/pages', 
                        'blocks',
                        ['hero', 'cta', 'features', 'testimonials', 'pricing'],
                        ['max_depth' => 4]
                    );
                },
                'url' => function ($url) {
                    return url($url);
                },
            ]
        ],
        
        'blog' => [
            'driver' => 'algolia',
            'searchables' => 'collection:blog',
            'fields' => ['title', 'content', 'excerpt', 'url'],
            'transformers' => [
                'content' => function ($content) {
                    return TransformBard::transform($content, 'collections/blog/blog', 'content');
                },
                'url' => function ($url) {
                    return url($url);
                },
            ]
        ],
    ],
    
    'drivers' => [
        'algolia' => [
            'credentials' => [
                'id' => env('ALGOLIA_APP_ID', ''),
                'secret' => env('ALGOLIA_SECRET', ''),
            ],
        ],
    ],
];
```

## Migrating from v1.x

The v2.0 API is backward compatible with v1.x usage. Your existing transformers will continue to work:

```php
// v1.x syntax (still supported)
'body' => function ($body) {
    return TransformBard::transform($body, 'collections/blog/article', 'body', ['columns', 'info_block']);
},

// v2.x syntax (recommended)
'body' => function ($body) {
    return TransformBard::transform(
        $body, 
        'collections/blog/article', 
        'body', 
        ['columns', 'info_block'],
        ['max_depth' => 3]
    );
},
```

## Error Handling

The transformer includes robust error handling:

- **Missing Blueprints**: Falls back to raw content processing if blueprint is not found
- **Malformed Content**: Gracefully handles unexpected data structures
- **Recursion Protection**: Prevents infinite loops with depth limiting
- **Memory Management**: Configurable content length limits

## Performance Considerations

- **Recursion Depth**: Set appropriate `max_depth` based on your content structure
- **Content Length**: Use `max_length` to prevent oversized search indexes
- **Caching**: Consider implementing application-level caching for large content volumes

## Troubleshooting

### Content Not Being Extracted

1. Check that the blueprint path and field name are correct
2. Verify that your content structure contains text-bearing fields
3. Increase `max_depth` if you have deeply nested content
4. Add specific set types to the `$userSetTypes` parameter

### Performance Issues

1. Reduce `max_depth` to limit recursion
2. Set a lower `max_length` value
3. Consider excluding certain set types if they don't contain relevant content

## Changelog

### v2.0.0
- Added Statamic 5 support
- Implemented deep content extraction
- Added configurable options
- Improved error handling
- Modern PHP 8.2+ requirements

### v1.x
- Original Statamic 3 support
- Basic Bard field transformation

## Acknowledgements

Special thanks to [Erin Dalzell](https://statamic.com/sellers/silentz) for the original inspiration and guidance.