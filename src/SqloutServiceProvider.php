<?php

namespace Baril\Sqlout;

use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Illuminate\Support\ServiceProvider;

class SqloutServiceProvider extends ServiceProvider
{
    /**
     * Register the application's scout macros.
     *
     * @return void
     */
    public function boot()
    {
        app(EngineManager::class)->extend('sqlout', function () {
            return new Engine;
        });

        Builder::macro('count', function () {
            return $this->engine()->getTotalCount(
                $this->engine()->search($this)
            );
        });

        Builder::macro('orderByScore', function () {
            return $this->orderBy('score', 'desc');
        });


        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([
            __DIR__.'/../config/scout.php' => $this->app['path.config'].DIRECTORY_SEPARATOR.'scout.php',
        ]);
    }
}