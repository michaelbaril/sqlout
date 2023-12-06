<?php

namespace Baril\Sqlout\Tests\Models;

use Baril\Sqlout\Searchable;
use Baril\Sqlout\Tests\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes, Searchable;

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

    protected static function newFactory()
    {
        return PostFactory::new();
    }
}
