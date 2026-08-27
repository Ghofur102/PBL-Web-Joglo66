<?php

namespace App\Traits;

trait HasDynamicConnection
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->connection = app()->environment('testing')
            ? 'testing'
            : 'mysql_joglo66_app';
    }
}
