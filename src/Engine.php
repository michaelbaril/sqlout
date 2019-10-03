<?php

namespace Baril\Sqlout;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use Laravel\Scout\Builder;

class Engine extends ScoutEngine
{
    /**
     * Apply the filters to the indexed content or search terms, tokenize it
     * and stem the words.
     *
     * @param string $content
     * @return string
     */
    protected function processString($content)
    {
        // Apply custom filters:
        foreach (config('scout.sqlout.filters', []) as $filter) {
            if (is_callable($filter)) {
                $content = call_user_func($filter, $content);
            }
        }

        // Tokenize:
        $words = preg_split(config('scout.sqlout.token_delimiter', '/[\s]+/'), $content);

        // Remove stopwords & short words:
        $minLength = config('scout.sqlout.minimum_length', 0);
        $stopwords = config('scout.sqlout.stopwords', []);
        $words = (new Collection($words))->reject(function ($word) use ($minLength, $stopwords) {
            return mb_strlen($word) < $minLength || in_array($word, $stopwords);
        })->all();

        // Stem:
        $stemmer = config('scout.sqlout.stemmer');
        if (is_string($stemmer) && method_exists($stemmer, 'stem')) {
            $stemmer = [new $stemmer, 'stem'];
        }
        if (is_callable($stemmer)) {
            foreach ($words as $k => $word) {
                $words[$k] = call_user_func($stemmer, $word);
            }
        }

        // Return result:
        return implode(' ', $words);
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function update($models)
    {
        $models->each(function ($model) {
            $type = $model->getMorphClass();
            $id = $model->getKey();
            SearchIndex::where('record_type', $type)->where('record_id', $id)->delete();

            $data = $model->toSearchableArray();
            foreach (array_filter($data) as $field => $content) {
                SearchIndex::create([
                    'record_type' => $type,
                    'record_id' => $id,
                    'field' => $field,
                    'weight' => $model->getSearchWeight($field),
                    'content' => $this->processString($content),
                ]);
            }
        });
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function delete($models)
    {
        if (!$models->count()) {
            return;
        }
        SearchIndex::where('record_type', $models->first()->getMorphClass())
                ->whereIn('record_id', $models->modelKeys())
                ->delete();
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'hitsPerPage' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'hitsPerPage' => $perPage,
            'page' => $page - 1,
        ]);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  array  $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $mode = $builder->mode ?? config('scout.sqlout.default_mode');
        $terms = $this->processString($builder->query);

        // Creating search query:
        $query = SearchIndex::query()
                ->with('record')
                ->where('record_type', $builder->model->getMorphClass())
                ->whereRaw("match(content) against (? $mode)", [$terms])
                ->groupBy('record_type')
                ->groupBy('record_id')
                ->selectRaw("sum(weight * (match(content) against (? $mode))) as _score", [$terms])
                ->addSelect(['record_type', 'record_id']);
        foreach ($builder->wheres as $field => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        // Order clauses:
        if (!$builder->orders) {
            $builder->orderByScore();
        }
        if ($builder->orders) {
            foreach ($builder->orders as $i => $order) {
                if ($order['column'] == '_score') {
                    $query->orderBy($order['column'], $order['direction']);
                    continue;
                }
                $alias = 'sqlout_reserved_order_' . $i;
                $subQuery = $builder->model->newQuery()
                        ->select([
                            $builder->model->getKeyName() . " as {$alias}_id",
                            $order['column'] . " as {$alias}_order",
                        ]);
                $query->joinSub($subQuery, $alias, function ($join) use ($alias) {
                    $join->on('record_id', '=', $alias . '_id');
                });
                $query->orderBy($alias . '_order', $order['direction']);
            }
        }

        // Applying scopes to the model query:
        $query->whereHasMorph('record', get_class($builder->model), function ($query) use ($builder) {
            foreach ($builder->scopes as $scope) {
                if ($scope instanceof Closure) {
                    $scope($query);
                } else {
                    list($method, $parameters) = $scope;
                    $query->$method(...$parameters);
                }
            }
        });

        // Applying limit/offset:
        if ($options['hitsPerPage'] ?? null) {
            $query->limit($options['hitsPerPage']);
            if ($options['page'] ?? null) {
                $offset = $options['page'] * $options['hitsPerPage'];
                $query->offset($offset);
            }
        }

        // Performing a first query to determine the total number of hits:
        $countQuery = $query->getQuery()
                ->cloneWithout(['groups', 'orders', 'offset', 'limit'])
                ->cloneWithoutBindings(['order']);
        $results = ['nbHits' => $countQuery->count($countQuery->getConnection()->raw('distinct record_id'))];

        // Performing the search itself:
        $results['hits'] = $query->with('record')->get();

        return $results;
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return $results['hits']->pluck('record_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        $models = $results['hits']->map(function ($hit) {
            $hit->record->_score = $hit->_score;
            return $hit->record;
        })->all();
        return $model->newCollection($models);
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['nbHits'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        SearchIndex::where('record_type', $model->getMorphClass())->delete();
    }
}
