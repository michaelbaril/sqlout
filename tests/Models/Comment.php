<?php

namespace Baril\Sqlout\Tests\Models;

use Baril\Sqlout\Searchable;
use Baril\Sqlout\Tests\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory, Searchable;

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function toSearchableArray()
    {
        return [
            'author' => $this->author,
            'text' => $this->text,
        ];
    }

    public function scopeAuthor($query, $author)
    {
        $query->where('author', $author);
    }

    protected static function newFactory()
    {
        return CommentFactory::new();
    }
}
