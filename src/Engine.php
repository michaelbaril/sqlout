<?php

namespace Baril\Sqlout;

use Closure;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use Laravel\Scout\Builder;

class Engine extends ScoutEngine
{
    protected function newSearchQuery($model)
    {
        $query = SearchIndex::query();
        $searchModel = $query->getModel()
                ->setConnection($model->getConnectionName())
                ->setTable($model->searchableAs());
        return $query->setModel($searchModel);
    }

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
        $stopwords = $this->loadStopWords();
        $words = (new Collection($words))->reject(function ($word) use ($minLength, $stopwords) {
            return mb_strlen($word) < $minLength || in_array($word, $stopwords);
        })->all();

        // Stem:
        $stemmer = config('scout.sqlout.stemmer');
        if ($stemmer) {
            if (is_string($stemmer) && class_exists($stemmer) && method_exists($stemmer, 'stem')) {
                $stemmer = [new $stemmer(), 'stem'];
            }
            if (is_object($stemmer) && method_exists($stemmer, 'stem')) {
                foreach ($words as $k => $word) {
                    $words[$k] = $stemmer->stem($word);
                }
            } elseif (is_callable($stemmer)) {
                foreach ($words as $k => $word) {
                    $words[$k] = call_user_func($stemmer, $word);
                }
            } else {
                throw new Exception('Invalid stemmer!');
            }
        }

        // Return result:
        return implode(' ', $words);
    }

    protected function loadStopWords()
    {
        $stopwords = config('scout.sqlout.stopwords', []);
        if (is_iterable($stopwords)) {
            return $stopwords;
        }

        $file = $stopwords;
        if (!file_exists($file)) {
            throw new Exception("Can't import stop words from $file");
        }

        $stream = fopen($file, 'r');
        $firstline = trim(fgets($stream));

        if (trim($firstline) == '<?php') {
            return require $file;
        }

        $stopwords = [$firstline];
        while (false !== ($word = fgets($stream))) {
            $stopwords[] = trim($word);
        }
        return $stopwords;
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
            $this->newSearchQuery($model)->where('record_type', $type)->where('record_id', $id)->delete();

            $data = $model->toSearchableArray();
            foreach (array_filter($data) as $field => $content) {
                $this->newSearchQuery($model)->create([
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
        $this->newSearchQuery($models->first())
                ->where('record_type', $models->first()->getMorphClass())
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
        $query = $this->newSearchQuery($builder->model)
                ->with('record')
                ->where('record_type', $builder->model->getMorphClass())
                ->whereRaw("match(content) against (? $mode)", [$terms])
                ->groupBy('record_type')
                ->groupBy('record_id')
                ->selectRaw("sum(weight * (match(content) against (? $mode))) as _score", [$terms])
                ->addSelect(['record_type', 'record_id']);

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

        $query->whereHasMorph('record', get_class($builder->model), function ($query) use ($builder) {
            $this->applyQueryScopes($builder, $query);
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

        // Preparing the actual query:
        $results['query'] = $query->with('record');

        return $results;
    }

    /**
     * @param  \Baril\Sqlout\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Query\Builder  $query
     * @return void
     */
    protected function applyQueryScopes($builder, $query)
    {
        $softDeleted = $builder->wheres['__soft_deleted'] ?? null;
        if (!method_exists($builder->model, 'bootSoftDeletes')) {
            // Model is not soft deletable
        } elseif (is_null($softDeleted)) {
            $query->withTrashed();
        } elseif ($softDeleted) {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }
        unset($builder->wheres['__soft_deleted']);
        foreach ($builder->wheres as $field => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
        foreach ($builder->whereIns ?? [] as $field => $values) {
            $query->whereIn($field, $values);
        }
        foreach ($builder->whereNotIns ?? [] as $field => $values) {
            $query->whereNotIn($field, $values);
        }
        foreach ($builder->scopes as $scope) {
            if ($scope instanceof Closure) {
                $scope($query);
            } else {
                list($method, $parameters) = $scope;
                $query->$method(...$parameters);
            }
        }
        if ($builder->queryCallback) {
            $callback = $builder->queryCallback;
            $callback($query);
        }
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return $results['query']->pluck('record_id')->values();
    }

    /**
     * Extract the Model from the search hit.
     *
     * @param SearchIndex $hit
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getRecord($hit)
    {
        $hit->record->_score = $hit->_score;
        return $hit->record;
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
        $models = $results['query']->get()->map(function ($hit) {
            return $this->getRecord($hit);
        })->all();
        return $model->newCollection($models);
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        return $results['query']->lazy()->map(function ($hit) {
            return $this->getRecord($hit);
        });
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
        $this->newSearchQuery($model)->where('record_type', $model->getMorphClass())->delete();
    }

    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array  $options
     * @return mixed
     */
    public function createIndex($name, array $options = [])
    {
        //
    }

    /**
     * Delete a search index.
     *
     * @param  string  $name
     * @return mixed
     */
    public function deleteIndex($name)
    {
        //
    }
}
