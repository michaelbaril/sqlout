<?php

namespace Baril\Sqlout;

use Closure;
use Laravel\Scout\Builder as ScoutBuilder;

class Builder extends ScoutBuilder
{
    const NATURAL_LANGUAGE = 'in natural language mode';
    const QUERY_EXPANSION = 'in natural language mode with query expansion';
    const BOOLEAN = 'in boolean mode';

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
    }

    /**
     * Include only soft deleted records in the results.
     *
     * @return $this
     */
    public function onlyTrashed()
    {
        $this->scopes[] = ['onlyTrashed', []];
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
