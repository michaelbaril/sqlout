<?php

namespace Baril\Sqlout;

use Baril\Sqlout\Console\MakeMigrationCommand;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

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

        //$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->commands([
            MakeMigrationCommand::class,
        ]);
        $this->publishes([
            __DIR__.'/../config/scout.php' => $this->app['path.config'].DIRECTORY_SEPARATOR.'scout.php',
        ]);
    }
}
