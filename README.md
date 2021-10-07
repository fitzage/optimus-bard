# Optimus Bard

> Optimus Bard takes the content from a Statamic Bard field and transforms it into a string when updating your search index

## Features

What this addon does:

- Receives a Bard field from a search transformer
- Combines all text blocks from this Bard field into a single string, along with the text from a user-definable list of sets
- Runs some opinionated cleanup on resulting string
- Returns the string back to the search transformer, which replaces the bard field in the search index

## How to Install

You can search for this addon in the `Tools > Addons` section of the Statamic control panel and click **install**, or run the following command from your project root:

``` bash
composer require fitzage/optimus-bard
```

## How to Use

Configure your search indexes using [the Statamic search documentation](https://statamic.dev/search).

In `config/statamic/search.php`, add the following at the top of the file, below the `<?php` line:

```php
use Fitzage\OptimusBard\TransformBard;
```

Also in `config/statamic/search.php`, add a transformer to your search index per [the Statamic documentation](https://statamic.dev/search#transforming-fields). The contents of the transformer should look like this:

```php
'field_name' => function ($field_name) {
    return TransformBard::transform($field_name, 'blueprint/path', 'field_name');
},
```

Your index configuration will now look something like this:

```php
'blog' => [
  'driver' => 'algolia',
  'searchables' => 'collection:blog',
  'fields' => ['title','description','body'],
  'transformers' => [
    'body' => function ($body) {
      return TransformBard::transform($body, 'collections/blog/article', 'body');
    },
  ]
],
```

## Acknowledgements

Special thanks to [Erin Dalzell](https://statamic.com/sellers/silentz) for sharing the code that got me started as well as additional assistance, including helping me come up with a creative name for my addon.
