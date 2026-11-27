<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\DB;

trait UsesInMemoryDatabase
{
    protected function setUpInMemoryDatabase(): void
    {
        $connection = config('database.connections.sqlite');
        $connection['database'] = ':memory:';
        config([
            'database.default' => 'mysql_joglo66_app',
            'database.connections.mysql_joglo66_app' => $connection,
        ]);
        DB::purge('mysql_joglo66_app');
    }
}
