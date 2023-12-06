<?php

namespace Baril\Sqlout\Tests;

use DB;
use Baril\Sqlout\Builder;
use Baril\Sqlout\SearchIndex;
use Baril\Sqlout\Tests\Models\Comment;
use Baril\Sqlout\Tests\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        $results = Comment::search('schtroumpf')->scope(function ($builder) {
            $builder->author('gargamel');
        })->get();
        $this->assertCount(1, $results);
        $this->assertEquals($comment->id, $results[0]->id);
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

    public function test_stopwords()
    {
        app('config')->set('scout.sqlout.stopwords', [
            'fuck',
        ]);

        $post = Post::first();
        $post->body = 'shut the fuck up donny';
        $post->save();

        $indexed = $this->newSearchQuery()->where('record_type', Post::class)->where('record_id', $post->id)->where('field', 'body')->value('content');
        $this->assertEquals('shut the up donny', $indexed);
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
        $results = $search->get()->all();
        $lazyResults = $search->cursor()->all();
        $this->assertCount(3, $lazyResults);
        $this->assertEquals($results, $lazyResults);
    }
}
