<?php

namespace Baril\Sqlout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;

class SearchIndex extends Model
{
    protected $fillable = [
        'record_type',
        'record_id',
        'field',
        'weight',
        'content',
    ];

    public function getTable(): string
    {
        return config('scout.sqlout.table_name');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function record()
    {
        return $this->morphTo();
    }
}
