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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        // Catatan: JANGAN panggil $middleware->statefulApi() di sini.
        // Itu memasang middleware milik Laravel Sanctum, sementara paket Sanctum
        // tidak dipasang di proyek ini — akibatnya seluruh route /api/* balas
        // error 500. Autentikasi API memakai guard token bawaan (auth:api).
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
