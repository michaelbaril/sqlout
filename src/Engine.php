<?php

namespace Baril\Sqlout;

use Laravel\Scout\Engines\Engine as ScoutEngine;
use Laravel\Scout\Builder;

class Engine extends ScoutEngine
{
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
            SearchIndex::type($type)->id($id)->delete();

            $data = $model->toSearchableArray();
            foreach (array_filter($data) as $field => $content) {
                SearchIndex::create([
                    'record_type' => $type,
                    'record_id' => $id,
                    'field' => $field,
                    'weight' => $model->weights[$field] ?? 1,
                    'content' => $content,
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
        $models->each(function ($model) {
            $type = $model->getMorphClass();
            $id = $model->getKey();
            SearchIndex::type($type)->id($id)->delete();
        });
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
        $query = SearchIndex::search(
            $builder->model->getMorphClass(),
            $builder->query
            // $mode = null
        );
        if ($builder->queryCallback) {
            // @todo trashed
            $query->whereHasMorph('record', $builder->model->getMorphClass(), clone $builder->queryCallback);
            $builder->queryCallback = null;
        } else {
            $query->hasMorph('record', $builder->model->getMorphClass());
        }

        foreach ($builder->wheres as $field => $value) {
            $query->where($field, $value);
        }

        $results = ['nbHits' => SearchIndex::totalCount($query)];

        foreach ($builder->orders as $order) {
            $query->orderBy($order['column'], $order['direction']);
        }

        if ($options['hitsPerPage'] ?? null) {
            $query->limit($options['hitsPerPage']);
            if ($options['page'] ?? null) {
                $offset = $options['page'] * $options['hitsPerPage'];
                $query->offset($offset);
            }
        }

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
            $hit->record->_score = $hit->score;
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
        SearchIndex::type($model->getMorphClass())->delete();
    }
}
