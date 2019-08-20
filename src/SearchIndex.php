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

    /**
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param mixed $type
     */
    public function scopeType($builder, $type)
    {
        if (is_array($type) || $type instanceof Arrayable) {
            $builder->whereIn('record_type', $type);
        } else {
            $builder->where('record_type', $type);
        }
    }

    /**
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param mixed $id
     */
    public function scopeId($builder, $id)
    {
        if (is_array($id) || $id instanceof Arrayable) {
            $builder->whereIn('record_id', $id);
        } else {
            $builder->where('record_id', $id);
        }
    }
}
