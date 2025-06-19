<?php

namespace Baril\Sqlout\Tests\Models;

class PostOtherSearchIndex extends Post
{
    protected $table = 'posts';

    public function searchableAs()
    {
        return 'other_searchindex';
    }
}
