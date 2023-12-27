<?php

namespace Baril\Sqlout\Migrations;

use Baril\Sqlout\Migrations\MigrationCreator;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand as BaseCommand;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

class MigrateMakeCommand extends BaseCommand
{
    protected $signature = 'sqlout:make-migration {connection? : Name of the connection}
        {--name= : The name of the migration.}
        {--path= : The location where the migration file should be created.}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths.}
        {--migrate : Migrate the database after the migration file has been created.}';
    protected $description = 'Create the migration file for Sqlout, and optionally run the migration';

    public function __construct(MigrationCreator $creator, Composer $composer)
    {
        parent::__construct($creator, $composer);
    }

    public function handle()
    {
        $connection = $this->input->getArgument('connection') ?? config('database.default');

        $this->writeSqloutMigration($connection);
        $this->composer->dumpAutoloads();

        if ($this->input->hasOption('migrate') && $this->option('migrate')) {
            $this->call('migrate');
        }
    }

    protected function writeSqloutMigration($connection)
    {
        // Get the name for the migration file:
        $name = $this->input->getOption('name') ?: 'create_sqlout_index_for_' . $connection;
        $name = Str::snake(trim($name));
        $className = Str::studly($name);
        $tableName = config('scout.sqlout.table_name');

        // Generate the content of the migration file:
        $contents = $this->getMigrationContents($className, $connection, $tableName);

        // Generate the file:
        $file = $this->creator->create(
            $name,
            $this->getMigrationPath(),
            $tableName,
            true
        );
        file_put_contents($file, $contents);

        // Output information:
        $file = pathinfo($file, PATHINFO_FILENAME);
        $this->line("<info>Created Migration:</info> {$file}");
    }

    protected function getMigrationContents($className, $connection, $tableName)
    {
        $contents = file_get_contents(__DIR__ . '/stubs/migration.stub');
        $contents = str_replace([
            'class CreateSqloutIndex',
            '::connection()',
            "config('scout.sqlout.table_name')",
        ], [
            'class ' . $className,
            "::connection('$connection')",
            "'$tableName'"
        ], $contents);
        $contents = preg_replace('/\;[\s]*\/\/.*\n/U', ";\n", $contents);
        return $contents;
    }
}
