<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case TREASURER = 'treasurer';
    case WORKER = 'worker';
    case TENANT = 'tenant';
    case ADMIN = 'admin';
}
