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
        // We could be using any version of Dotenv since 2.x:
        if (method_exists(Dotenv::class, 'createImmutable')) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        } elseif (method_exists(Dotenv::class, 'create')) {
            $dotenv = Dotenv::create(dirname(__DIR__));
        } else {
            $dotenv = new Dotenv(dirname(__DIR__));
        }
        $dotenv->load();
        $app['config']->set('database.default', 'sqlout');
        $app['config']->set('database.connections.sqlout', [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT'),
            'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
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
        $this->withFactories(__DIR__ . '/database/factories');
        \DB::enableQueryLog();
    }

    protected function dumpQueryLog()
    {
        dump(\DB::getQueryLog());
    }
}
