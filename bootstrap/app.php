<?php

use App\Http\Middleware\AuthenticateWithApiKey;
use App\Http\Middleware\TriggerAutomaticPeckDatabaseRefresh;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');

        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*'
                    ? '*'
                    : array_map('trim', explode(',', $trustedProxies)),
            );
        }

        $middleware->web(append: [
            TriggerAutomaticPeckDatabaseRefresh::class,
        ]);

        $middleware->alias([
            'api.key' => AuthenticateWithApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
