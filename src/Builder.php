<?php

namespace Baril\Sqlout;

use Closure;
use Laravel\Scout\Builder as ScoutBuilder;

class Builder extends ScoutBuilder
{
    public const NATURAL_LANGUAGE = 'in natural language mode';
    public const QUERY_EXPANSION = 'in natural language mode with query expansion';
    public const BOOLEAN = 'in boolean mode';

    /**
     * The search mode (one of the consts above).
     *
     * @var string
     */
    public $mode;

    /**
     * The array of scopes that will be applied to the model query.
     *
     * @var array
     */
    public $scopes = [];

    /**
     * Create a new search builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $query
     * @param  \Closure  $callback
     * @param  bool  $softDelete
     * @return void
     */
    public function __construct($model, $query, $callback = null, $softDelete = false)
    {
        parent::__construct($model, $query, $callback, $softDelete);
        if ($softDelete) {
            unset($this->wheres['__soft_deleted']);
        }
    }

    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return parent::__call($method, $parameters);
        }

        $this->scopes[] = [$method, $parameters];
        return $this;
    }

    public function scope(Closure $callback)
    {
        $this->scopes[] = $callback;
        return $this;
    }

    /**
     * Add a constraint to the search query.
     *
     * @param  string  $field
     * @param  mixed  $value
     * @return $this
     */
    public function where($field, $value)
    {
        $args = func_get_args();
        $this->scopes[] = ['where', $args];
        return $this;
    }

    /**
     * Include soft deleted records in the results.
     *
     * @return $this
     */
    public function withTrashed()
    {
        $this->scopes[] = ['withTrashed', []];
        return $this;
    }

    /**
     * Include only soft deleted records in the results.
     *
     * @return $this
     */
    public function onlyTrashed()
    {
        $this->scopes[] = ['onlyTrashed', []];
        return $this;
    }

    /**
     * Order the query by score.
     *
     * @param  string  $direction
     * @return $this
     */
    public function orderByScore($direction = 'desc')
    {
        return $this->orderBy('_score', $direction);
    }

    /**
     * Restrict the search to the provided field(s).
     *
     * @param string|array|\Illuminate\Contracts\Support\Arrayable $fields
     * @return $this
     */
    public function only($fields)
    {
        return parent::where('field', $fields);
    }

    /**
     * Switches to the provided mode.
     *
     * @param string $mode
     * @return $this
     */
    public function mode($mode)
    {
        $this->mode = trim(strtolower($mode));
        return $this;
    }

    /**
     * Switches to natural language mode.
     *
     * @param string $mode
     * @return $this
     */
    public function inNaturalLanguageMode()
    {
        return $this->mode(static::NATURAL_LANGUAGE);
    }

    /**
     * Switches to natural language mode with query expansion.
     *
     * @param string $mode
     * @return $this
     */
    public function withQueryExpansion()
    {
        return $this->mode(static::QUERY_EXPANSION);
    }

    /**
     * Switches to boolean mode.
     *
     * @param string $mode
     * @return $this
     */
    public function inBooleanMode()
    {
        return $this->mode(static::BOOLEAN);
    }

    /**
     * Returns the total number of hits
     *
     * @return int
     */
    public function count()
    {
        return $this->engine()->getTotalCount(
            $this->engine()->search($this)
        );
    }
}
