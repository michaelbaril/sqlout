<?php

namespace Baril\Sqlout;

use Baril\Sqlout\Migrations\MigrateMakeCommand;
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateMakeCommand::class,
            ]);
        }
        $this->publishes([
            __DIR__.'/../config/scout.php' => $this->app['path.config'].DIRECTORY_SEPARATOR.'scout.php',
        ]);
    }
}
