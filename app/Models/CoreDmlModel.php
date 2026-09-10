<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class CoreDmlModel extends Model
{
    public const DML_CONNECTION = 'mysql_joglo66_app';

    protected $connection = self::DML_CONNECTION;
}
