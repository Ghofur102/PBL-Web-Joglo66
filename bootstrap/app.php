<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SkipNgrokWarning;
use App\Http\Middleware\CheckFieldAdmin;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*'); //
        $middleware->alias([
            'check.field.admin' => CheckFieldAdmin::class,
        ]);
        $middleware->redirectUsersTo(fn () => route('tenant.booking.dashboard'));
        $middleware->preventRequestForgery(except: [
            '/duitku/callback',
            '/api/duitku/callback',
            'duitku/*',
            'api/duitku/*',
        ]);
        $middleware->append(SkipNgrokWarning::class); 
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
