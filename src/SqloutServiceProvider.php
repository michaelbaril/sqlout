<?php

namespace Baril\Sqlout;

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

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([
            __DIR__.'/../config/scout.php' => $this->app['path.config'].DIRECTORY_SEPARATOR.'scout.php',
        ]);
    }
}
