<?php

namespace Baril\Sqlout;

use Illuminate\Database\Eloquent\Model;

class SearchIndex extends Model
{
    protected $fillable = [
        'record_type',
        'record_id',
        'field',
        'weight',
        'content',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function record()
    {
        return $this->morphTo();
    }
}
