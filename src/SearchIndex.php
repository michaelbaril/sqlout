<?php

namespace Baril\Sqlout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;

class SearchIndex extends Model
{
    protected $table = 'searchindex';
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

    /**
     *
     * @param string $type
     * @param string $terms
     * @param string $mode Search mode: "natural language" or "boolean"
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function search($type, $terms, $mode = null)
    {
        $mode = $mode ?? 'natural language';
        $query = static::query()
                ->with('record')
                ->type($type)
                ->whereRaw("match(content) against (? in $mode mode)", [$terms])
                ->groupBy('record_type')
                ->groupBy('record_id');
        if ($mode !== 'boolean') {
            $query->selectRaw("sum(weight * (match(content) against (? in $mode mode))) as score", [$terms]);
        }
        $query->addSelect(['record_type', 'record_id']);
        return $query;
    }

    /**
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return int
     */
    public static function totalCount($query)
    {
        $query = clone $query;
        $query->getQuery()->groups = null;
        return $query->count((new static)->getConnection()->raw('distinct record_id'));
    }
}
