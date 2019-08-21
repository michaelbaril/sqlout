# Sqlout

Sqlout is a very simple MySQL driver for Laravel Scout. It indexes the data into
a dedicated table of the MySQL database, and uses a fulltext index to search.
It is meant for small-sized projects, for which bigger solutions such as
ElasticSearch would be an overkill.

## Setup

Require the package:

```
composer require baril/sqlout
```

Publish the configuration:

```
php artisan vendor:publish
```

Add the service providers (Scout's and Sqlout's) to your `config/app.php` file:

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
php artisan migrate
```

This will create a `searchindex` table in your database (the table name can
be customized in the config file).

## Making a model searchable

The process is similar to what is described in
[Scout's documentation](https://laravel.com/docs/5.8/scout#configuration),
with a few differences (listed below).

```php
class Post extends Model
{
    use \Baril\Sqlout\Searchable;

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

* You'll notice that the model uses the `Baril\Sqlout\Searchable` trait
instead of `Laravel\Scout\Searchable`.
* The `$weight` property can be used to "boost" some fields. The default value
is 1.

Once this is done, you can index your data using Scout's Artisan command:

```
php artisan scout:import "App\Post"
```

Of course, your models will also be indexed automatically on save.

## Searching

### Basics

```php
$results = Post::search('this rug really tied the room together')->get();
$results = Post::search('this rug really tied the room together')->withTrashed()->get();
```

See [Scout's documentation](https://laravel.com/docs/5.8/scout#searching)
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
as if it was a query builder on the model:

```php
$results = Post::search('this rug really tied the room together')
    ->published() // the `published` scope is defined in the Post class
    ->get();
```

> :warning: If your scope adds an `order by` clause, it won't be applied.
> See below the proper way to order results.

If the name of your scope collides with the name of a method of the `Builder`
object, you can use the `scope` method:

```php
$results = Post::search('this rug really tied the room together')
    ->scope(function ($query) {
        $query->within('something');
    })
    ->get();
```

Similarly, all calls to the `where` method on the search builder will be
forwarded to the model's query builder.

### Search modes

MySQL's fulltext search comes in 3 flavours:
* natural language mode,
* natural language mode with query expansion,
* boolean mode.

Sqlout's default mode is "natural language", but this can be changed in the
config file.

You can switch between all 3 modes on a per-query basis by using the following
methods:

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
        'stemmer' => Wamania\Snowball\French::class,
    ],
];
```

In the example, the stemmer comes from the package
[`wamania/php-stemmer`](https://github.com/wamania/php-stemmer), but any class
with a `stem` method, or anything callable such as a closure, will do.
