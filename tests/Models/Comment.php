<?php

namespace Baril\Sqlout\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Baril\Sqlout\Searchable;

class Comment extends Model
{
    use Searchable;

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
}
