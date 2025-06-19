<?php

namespace Baril\Sqlout;

use Laravel\Scout\Searchable as ScoutSearchable;

trait Searchable
{
    use ScoutSearchable;

    /**
     * Perform a search against the model's indexed data.
     *
     * @param  string  $query
     * @param  \Closure  $callback
     * @return \Laravel\Scout\Builder
     */
    public static function search($query = '', $callback = null)
    {
        return app(Builder::class, [
            'model' => new static(),
            'query' => $query,
            'callback' => $callback,
            'softDelete' => static::usesSoftDelete() && config('scout.soft_delete', false),
        ]);
    }

    /**
     * Get the weight of the specified field.
     *
     * @param string $field
     * @return int
     */
    public function getSearchWeight($field)
    {
        return $this->weights[$field] ?? 1;
    }

    /**
     * Get the index name for the model when searching.
     *
     * @return string
     */
    public function searchableAs()
    {
        return config(
            'scout.sqlout.table_name',
            config('scout.prefix').$this->getTable().config('scout.suffix', '_index')
        );
    }
}
