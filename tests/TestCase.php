<?php

namespace Baril\Sqlout\Tests;

use Baril\Sqlout\SqloutServiceProvider;
use Dotenv\Dotenv;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__), '.env.test');
        $dotenv->load();
        $app['config']->set('database.default', 'test');
        $app['config']->set('database.connections.test', [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT'),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'prefix'   => '',
        ]);
        $app['config']->set('scout', require __DIR__ . '/../config/scout.php');
    }

    protected function getPackageProviders($app)
    {
        return [
            ScoutServiceProvider::class,
            SqloutServiceProvider::class,
        ];
    }

    protected function setUp() : void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        \DB::enableQueryLog();
    }

    protected function dumpQueryLog()
    {
        dump(\DB::getQueryLog());
    }
}
