<?php

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
        $middleware->web(prepend: [\App\Http\Middleware\ForceRequestUrl::class]);
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);
        $middleware->api(prepend: [\App\Http\Middleware\SetApiLocale::class]);
        $middleware->alias([
            'auth.web' => \App\Http\Middleware\AuthenticateWeb::class,
            'password.enforced' => \App\Http\Middleware\EnforcePasswordChange::class,
            'sanctum.password' => \App\Http\Middleware\EnforcePasswordChangeForSanctum::class,
            'super-admin' => \App\Http\Middleware\SuperAdminOnly::class,
            'menu.permission' => \App\Http\Middleware\EnforceMenuPermission::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
