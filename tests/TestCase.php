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
        $this->loadEnv(['.env.test', '.env']);
        $this->setupDatabase($app, env('DB_ENGINE', 'sqlite'));
        $app['config']->set('scout', require __DIR__ . '/../config/scout.php');
    }

    protected function loadEnv($file)
    {
        if (is_array($file)) {
            foreach ($file as $f) {
                $this->loadEnv($f);
            }
            return;
        }
        if (file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . $file)) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__), $file);
            $dotenv->load();
        }
    }

    protected function setupDatabase($app, $engine = 'mysql')
    {
        $method = 'setup' . ucfirst($engine);
        method_exists($this, $method) ? $this->$method($app) : $this->setupOtherSgbd($app, $engine);
        $app['config']->set('database.default', $engine);
    }

    protected function setupSqlite($app)
    {
        $database = env('SQLITE_DATABASE', database_path('database.sqlite'));
        if (file_exists($database)) {
            unlink($database);
        }
        touch($database);

        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ]);
    }

    protected function setupMariadb($app)
    {
        $engine = class_exists(\Illuminate\Database\MariaDbConnection::class)
            ? 'mariadb'
            : 'mysql';
        $this->setupOtherSgbd($app, $engine);
        $app['config']->set('database.connections.mariadb', $app['config']["database.connections.$engine"]);
    }

    protected function setupSqlsrv($app)
    {
        $this->setupOtherSgbd($app, 'sqlsrv');
        $app['config']->set('database.connections.sqlsrv.trust_server_certificate', true);
    }

    protected function setupOtherSgbd($app, $engine)
    {
        $envPrefix = strtoupper($engine);
        $app['config']->set("database.connections.$engine", [
            'driver' => $engine,
            'host' => env('DB_HOST'),
            'port' => env("{$envPrefix}_PORT"),
            'database' => env("{$envPrefix}_DATABASE", env('DB_DATABASE')),
            'username' => env("{$envPrefix}_USERNAME", env('DB_USERNAME')),
            'password' => env("{$envPrefix}_PASSWORD", env('DB_PASSWORD')),
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            ScoutServiceProvider::class,
            SqloutServiceProvider::class,
        ];
    }

    protected function setUp(): void
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
