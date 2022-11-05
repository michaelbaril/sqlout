# Sqlout

Sqlout is a very simple MySQL driver for Laravel Scout. It indexes the data into
a dedicated table of the MySQL database, and uses a fulltext index to search.
It is meant for small-sized projects, for which bigger solutions such as
ElasticSearch would be an overkill.

Sqlout is different than Scout 9's native `Database` engine because it indexes
data in a separate, dedicated table, and uses a fulltext index. Sqlout has more
features such as field weights and word stemming.

Sqlout is compatible with Laravel 5.8+ to 9.x and Scout 7.1+ / 8.x / 9.x
(credit goes to [ikari7789](https://github.com/ikari7789) for Laravel 9 / Scout
9 support).

## Version Compatibility

 Laravel   | Scout     | Sqlout
:----------|:----------|:----------
 5.8       | 7.1 / 7.2 | 1.x / 2.0
 6.x       | 7.1 / 7.2 | 1.x / 2.0
 6.x       | 8.x       | 2.0
 7.x       | 8.x       | 2.0
 8.x       | 8.x       | 3.x
 8.x / 9.x | 9.x       | 4.x

## Setup

Require the package:

```
composer require baril/sqlout
```

Publish the configuration:

```
php artisan vendor:publish
```

If you're not using package discovery, manually add the service providers
(Scout's and Sqlout's) to your `config/app.php` file:

```php
return [
    // ...
    'providers' => [
        // ...
        Laravel\Scout\ScoutServiceProvider::class,
        Baril\Sqlout\SqloutServiceProvider::class,
    ],
];
```

Migrate your database:

```
php artisan sqlout:make-migration
php artisan migrate
```

This will create a `searchindex` table in your database (the table name can
be customized in the config file).

If you want to index models that belong to different connections, you need
a table for Sqlout on each connection. To create the table on a connection that
is not the default connection, you can call the `sqlout:make-migration` command
and pass the name of the connection:

```
php artisan sqlout:make-migration my_other_connection
php artisan migrate
```

## Making a model searchable

```php

use Baril\Sqlout\Searchable;

class Post extends Model
{
    use Searchable;

    protected $weights = [
        'title' => 4,
        'excerpt' => 2,
    ];

    public function toSearchableArray()
    {
        return [
            'title' => $this->post_title,
            'excerpt' => $this->post_excerpt,
            'body' => $this->post_content,
        ];
    }
}
```

The example above is similar to what is described in
[Scout's documentation](https://laravel.com/docs/master/scout#configuration),
with the following differences/additions:

* You'll notice that the model uses the `Baril\Sqlout\Searchable` trait
instead of `Laravel\Scout\Searchable`.
* The `$weight` property can be used to "boost" some fields. The default value
is 1.

Once this is done, you can index your data using Scout's Artisan command:

```
php artisan scout:import "App\Post"
```

Your models will also be indexed automatically on save.

## Searching

### Basics

```php
$results = Post::search('this rug really tied the room together')->get();
$results = Post::search('the dude abides')->withTrashed()->get();
```

See [Scout's documentation](https://laravel.com/docs/master/scout#searching)
for more details.

Sqlout's builder also provides the following additional methods:

```php
// Restrict the search to some fields only:
$builder->only('title');
$builder->only(['title', 'excerpt']);
// (use the same names as in the toSearchableArray method)

// Retrieve the total number of results:
$nbHits = $builder->count();
```

### Using scopes

With Sqlout, you can also use your model scopes on the search builder,
as if it was a query builder on the model itself. Similarly, all calls to the
`where` method on the search builder will be
forwarded to the model's query builder.

```php
$results = Post::search('you see what happens larry')
    ->published() // the `published` scope is defined in the Post class
    ->where('date', '>', '2010-10-10')
    ->get();
```

> :warning: Keep in mind that these forwarded scopes will actually be applied
> to a subquery (the main query here being the one on the `searchindex` table).
> This means that for example a scope that adds an `order by` clause won't have
> any effect. See below for the proper way to order results.

If the name of your scope collides with the name of a method of the
`Baril\Sqlout\Builder` object, you can wrap your scope into the `scope` method:

```php
$results = Post::search('ve vant ze money lebowski')
    ->scope(function ($query) {
        $query->within('something');
    })
    ->get();
```

### Search modes

MySQL's fulltext search comes in 3 flavours:
* natural language mode,
* natural language mode with query expansion,
* boolean mode.

Sqlout's default mode is "natural language" (but this can be changed in the
config file).

You can also switch between all 3 modes on a per-query basis, by using the
following methods:

```php
$builder->inNaturalLanguageMode();
$builder->withQueryExpansion();
$builder->inBooleanMode();
```

### Ordering the results

If no order is specified, the results will be ordered by score (most relevant
first). But you can also order the results by any column of your table.

```php
$builder->orderBy('post_status', 'asc')->orderByScore();
// "post_status" is a column of the original table
```

In the example below, the results will be ordered by status first, and then
by descending score.

### Filters, tokenizer, stopwords and stemming

In your config file, you can customize the way the indexed content and search
terms will be processed:

```php
return [
    // ...
    'sqlout' => [
        // ...
        'filters' => [ // anything callable (function name, closure...)
            'strip_tags',
            'html_entity_decode',
            'mb_strtolower',
            'strip_punctuation', // this helper is provided by Sqlout (see helpers.php)
        ],
        'token_delimiter' => '/[\s]+/',
        'minimum_length' => 2,
        'stopwords' => [
            'est',
            'les',
        ],
        'stemmer' => Wamania\Snowball\StemmerFactory::create('french'),
    ],
];
```

In the example, the stemmer comes from the package [`wamania/php-stemmer`],
but any class with a `stem` method, or anything callable such as a closure, will do.

[`wamania/php-stemmer`]: https://github.com/wamania/php-stemmer
