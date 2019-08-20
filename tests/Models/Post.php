<?php

namespace Baril\Sqlout\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Baril\Sqlout\Searchable;

class Post extends Model
{
    use Searchable;

    protected $weights = [
        'title' => 4,
    ];

    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
