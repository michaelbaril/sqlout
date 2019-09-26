<?php

namespace Baril\Sqlout\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Baril\Sqlout\Searchable;

class Post extends Model
{
    use SoftDeletes, Searchable;

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
