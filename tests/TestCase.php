<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected string $testDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDbPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'testing_unit.sqlite';
        if (!file_exists($this->testDbPath)) {
            touch($this->testDbPath);
        }

        $sqlite = [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => $this->testDbPath,
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ];

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => $sqlite,
            'database.connections.mysql_joglo66_app' => $sqlite,
            'logging.default' => 'null',
        ]);

        DB::purge('sqlite');
        DB::purge('mysql_joglo66_app');

        $this->artisan('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::disconnect('mysql_joglo66_app');

        if (isset($this->testDbPath) && file_exists($this->testDbPath)) {
            @unlink($this->testDbPath);
        }

        parent::tearDown();
    }
}
