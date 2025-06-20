<?php

namespace Baril\Sqlout\Tests;

use Baril\Sqlout\Builder;
use Baril\Sqlout\SearchIndex;
use Baril\Sqlout\Tests\Models\Comment;
use Baril\Sqlout\Tests\Models\Post;
use Baril\Sqlout\Tests\Models\PostOtherSearchIndex;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB as DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder as ScoutBuilder;
use Orchestra\Testbench\Database\MigrateProcessor;
use Wamania\Snowball\StemmerFactory;

class SearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Post::factory()->count(5)->create();
        Comment::factory()->count(5)->create();
    }

    protected function newSearchQuery()
    {
        $query = SearchIndex::query();
        $model = $query->getModel();
        $model->setTable(config('scout.sqlout.table_name'));
        $query->setModel($model);
        return $query;
    }

    public function test_index()
    {
        $indexed = $this->newSearchQuery()->groupBy('record_type')->selectRaw('record_type, count(*) as count')->pluck('count', 'record_type')->all();
        $this->assertArrayHasKey(Comment::class, $indexed);
        $this->assertArrayHasKey(Post::class, $indexed);
        $this->assertEquals(5 * 2, $indexed[Comment::class]);
        $this->assertEquals(5 * 2, $indexed[Post::class]);
    }

    public function test_simple_search()
    {
        $post = Post::first();
        $post->title = 'gloubiboulga';
        $post->save();

        $search = Post::search('gloubiboulga');
        $this->assertEquals(1, $search->count());
        $results = $search->get();
        $this->assertEquals($post->id, $results->first()->id);
    }

    public function test_paginated_search()
    {
        Post::all()->each(function ($post) {
            $post->title = 'gloubiboulga';
            $post->save();
        });

        $search = Post::search('gloubiboulga')->paginate(2);
        $this->assertEquals(2, $search->count());
        $this->assertEquals(5, $search->total());
        $this->assertEquals(3, $search->lastPage());
    }

    public function test_search_by_model()
    {
        $post = Post::first();
        $post->title = 'tralalatsointsoin';
        $post->save();

        $comment = Comment::first();
        $comment->text = 'tralalatsointsoin';
        $comment->save();

        $search = Comment::search('tralalatsointsoin');
        $this->assertEquals(1, $search->count());
    }

    public function test_search_with_weight()
    {
        $posts = Post::all();
        $posts[3]->title = 'schtroumpf';
        $posts[3]->save();
        $posts[2]->body = 'schtroumpf';
        $posts[2]->save();

        $results = Post::search('schtroumpf')->orderByScore()->get();
        $this->assertCount(2, $results);
        $this->assertEquals($posts[3]->id, $results[0]->id);
        $this->assertEquals($posts[2]->id, $results[1]->id);
    }

    public function test_restricted_search()
    {
        $posts = Post::all();
        $posts[3]->title = 'schtroumpf';
        $posts[3]->save();
        $posts[2]->body = 'schtroumpf';
        $posts[2]->save();

        $results = Post::search('schtroumpf')->only(['body'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals($posts[2]->id, $results[0]->id);
    }

    public function test_wheres()
    {
        Comment::query()->update([
            'author' => 'kiki',
            'text' => 'kuku',
            'post_id' => 1,
        ]);
        Comment::all()->searchable();
        $comments = Comment::all();
        $comments[0]->update([
            'author' => 'toto',
            'post_id' => 2,
        ]);
        $comments[1]->update([
            'author' => 'toto',
        ]);
        $comments[2]->update([
            'author' => 'tutu',
            'post_id' => 2,
        ]);

        $search = Comment::search('kuku')->where('author', 'toto');
        $this->assertEquals(2, $search->count());
        $this->assertEquals(2, $search->get()->count());

        if (method_exists(Builder::class, 'whereIn')) {
            $search = Comment::search('kuku')->whereIn('author', ['toto', 'tutu']);
            $this->assertEquals(3, $search->count());
            $this->assertEquals(3, $search->get()->count());

            $search = Comment::search('kuku')
                ->whereIn('author', ['toto', 'kiki'])
                ->where('post_id', 1);
            $this->assertEquals(3, $search->count());
            $this->assertEquals(3, $search->get()->count());
        }

        if (method_exists(Builder::class, 'whereNotIn')) {
            $search = Comment::search('kuku')->whereNotIn('author', ['toto', 'tutu']);
            $this->assertEquals(2, $search->count());
            $this->assertEquals(2, $search->get()->count());
        }
    }

    public function test_forwarded_scope()
    {
        Comment::query()->update(['text' => 'schtroumpf']);
        Comment::all()->searchable();
        $comment = Comment::first();
        $comment->author = 'gargamel';
        $comment->save();

        $this->assertEquals(5, Comment::search('schtroumpf')->count());

        $results = Comment::search('schtroumpf')->author('gargamel')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($comment->id, $results[0]->id);

        $results = Comment::search('schtroumpf')->query(function ($builder) {
            $builder->author('gargamel');
        })->get();
        $this->assertCount(1, $results);
        $this->assertEquals($comment->id, $results[0]->id);
    }

    public function test_macro_has_priority_over_scope()
    {
        ScoutBuilder::macro('author', function () {
            return 'gargamel';
        });
        $this->assertEquals('gargamel', Comment::search('schtroumpf')->author());
    }

    public function test_ordering()
    {
        $posts = Post::all();
        $posts[3]->title = 'schtroumpf';
        $posts[3]->save();
        $posts[2]->title = 'gargamel';
        $posts[2]->body = 'schtroumpf';
        $posts[2]->save();

        // Order by score by default:
        $results = Post::search('schtroumpf')->get();
        $this->assertEquals($posts[3]->id, $results[0]->id);
        $this->assertEquals($posts[2]->id, $results[1]->id);

        $results = Post::search('schtroumpf')->orderBy('title')->get();
        $this->assertEquals($posts[2]->id, $results[0]->id);
        $this->assertEquals($posts[3]->id, $results[1]->id);
    }

    public function test_search_modes()
    {
        app('config')->set('scout.sqlout.default_mode', Builder::BOOLEAN);

        Post::search('coucou')->get();
        $log = DB::getQueryLog();
        $query = end($log)['query'];
        $this->assertStringContainsString(Builder::BOOLEAN, $query);

        Post::search('kiki')->inNaturalLanguageMode()->get();
        $log = DB::getQueryLog();
        $query = end($log)['query'];
        $this->assertStringContainsString(Builder::NATURAL_LANGUAGE, $query);

        app('config')->set('scout.sqlout.default_mode', Builder::NATURAL_LANGUAGE);

        Post::search('kiki')->inBooleanMode()->get();
        $log = DB::getQueryLog();
        $query = end($log)['query'];
        $this->assertStringContainsString(Builder::BOOLEAN, $query);

        Post::search('kiki')->withQueryExpansion()->get();
        $log = DB::getQueryLog();
        $query = end($log)['query'];
        $this->assertStringContainsString(Builder::QUERY_EXPANSION, $query);
    }

    public function test_filters()
    {
        app('config')->set('scout.sqlout.filters', [
            'strip_tags',
            'html_entity_decode',
        ]);

        $post = Post::first();
        $post->body = '<p>salut &ccedil;a boume ?</p>';
        $post->save();

        $indexed = $this->newSearchQuery()->where('record_type', Post::class)->where('record_id', $post->id)->where('field', 'body')->value('content');
        $this->assertEquals('salut ça boume ?', $indexed);
    }

    /**
     * @dataProvider stopWordsProvider
     */
    public function test_stopwords($config, $content, $expectedIndexedContent)
    {
        app('config')->set('scout.sqlout.stopwords', $config);

        $post = Post::first();
        $post->body = $content;
        $post->save();

        $indexed = $this->newSearchQuery()->where('record_type', Post::class)->where('record_id', $post->id)->where('field', 'body')->value('content');
        $this->assertEquals($expectedIndexedContent, $indexed);
    }

    public static function stopWordsProvider()
    {
        return [
            'array' => [
                ['fuck'],
                'shut the fuck up donny',
                'shut the up donny',
            ],
            'PHP file' => [
                'vendor/voku/stop-words/src/voku/helper/stopwords/fr.php',
                'banco charlie alpha bravo',
                'charlie alpha bravo',
            ],
            'TXT file' => [
                'vendor/yooper/stop-words/data/stop-words_french_1_fr.txt',
                'bigre boum tsoin brrr kiki',
                'kiki',
            ],
        ];
    }

    public function test_minimum_length()
    {
        app('config')->set('scout.sqlout.minimum_length', 4);

        $post = Post::first();
        $post->body = 'shut the fuck up donny';
        $post->save();

        $indexed = $this->newSearchQuery()->where('record_type', Post::class)->where('record_id', $post->id)->where('field', 'body')->value('content');
        $this->assertEquals('shut fuck donny', $indexed);
    }

    public function test_stemming()
    {
        $posts = Post::limit(2)->get();

        $posts[0]->body = 'les chaussettes de l\'archiduchesse sont-elles sèches archi-sèches';
        $posts[0]->save();
        $posts[1]->body = 'la cigale ayant chanté tout l\'été se trouva fort dépourvue quand la bise fut venue';
        $posts[1]->save();

        Post::whereNotIn('id', [$posts[0]->id, $posts[1]->id])->get()->unsearchable();

        $this->assertEquals(0, Post::search('chanter')->count());

        app('config')->set('scout.sqlout.stemmer', StemmerFactory::create('french'));
        $posts->searchable();

        $this->assertEquals(1, Post::search('chanter')->count());
        $this->assertEquals(1, Post::search('chantées')->count());
        $this->assertEquals(1, Post::search('sèche')->count());
        $this->assertEquals(1, Post::search('chaussette')->count());
    }

    public function test_closure_as_stemmer()
    {
        $closure = function ($word) {
            return 'tralalatsointsoin';
        };
        app('config')->set('scout.sqlout.stemmer', $closure);
        Post::first()->searchable();
        $this->assertEquals(1, Post::search('tralalatsointsoin')->count());
    }

    public function test_soft_delete()
    {
        app('config')->set('scout.soft_delete', true);
        Post::query()->update(['body' => 'does marsellus wallace look like a bitch']);
        Post::all()->searchable();
        Post::first()->delete();
        $this->assertEquals(4, Post::search('bitch')->count());
        $this->assertEquals(5, Post::search('bitch')->withTrashed()->count());
        $this->assertEquals(1, Post::search('bitch')->onlyTrashed()->count());
    }

    public function test_morph_map()
    {
        Relation::morphMap([
            Post::class,
        ]);
        Post::query()->update(['title' => 'testing morphs']);
        Post::all()->searchable();
        $this->assertEquals(5, Post::search('morphs')->count());
    }

    public function test_lazy()
    {
        Post::skip(1)->take(3)->get()->each(function ($post) {
            $post->title = 'kikikuku';
            $post->save();
        });

        $search = Post::search('kikikuku');
        $results = $search->get();
        $lazyResults = $search->cursor();
        $this->assertInstanceOf(LazyCollection::class, $lazyResults);
        $this->assertCount(3, $lazyResults);
        $this->assertEquals($results->all(), $lazyResults->all());
    }

    public function test_limit()
    {
        Post::query()->update(['title' => 'testing limit']);
        Post::all()->searchable();

        $search = Post::search('limit')->take(1);
        $this->assertEquals(1, $search->get()->count());
    }

    public function test_paginate()
    {
        Post::query()->update(['title' => 'testing paginate']);
        Post::all()->searchable();
        $ids = Post::pluck('id')->sort();

        $paginator = Post::search('paginate')->orderBy('id')->simplePaginate(2, 'page', 2);
        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertCount(2, $paginator->items());
        $this->assertEquals(2, $paginator->currentPage());
        $this->assertEquals(
            $ids->skip(2)->take(2)->values()->all(),
            array_map(function ($item) {
                return $item->id;
            }, $paginator->items())
        );
    }

    public function test_other_index()
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations';
        File::deleteDirectory($path);
        File::makeDirectory($path);

        $this->artisan('sqlout:make-migration', [
            '--model' => PostOtherSearchIndex::class,
            '--path' => $path,
            '--realpath' => true,
        ]);

        $this->assertCount(1, File::allFiles($path));

        $migrator = new MigrateProcessor($this, [
            '--path' => $path,
            '--realpath' => true,
        ]);
        $migrator->up();

        $this->assertTrue(DB::getSchemaBuilder()->hasTable('other_searchindex'));

        PostOtherSearchIndex::unguard();
        PostOtherSearchIndex::create([
            'title' => 'kiki',
            'body' => 'kuku',
        ]);

        $this->assertNotEmpty(DB::table('other_searchindex')->get());

        $search = PostOtherSearchIndex::search('kiki');
        $this->assertEquals(1, $search->count());

        // Cleaning stuff:
        File::deleteDirectory($path);
    }
}
