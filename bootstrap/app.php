<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        // Klien API (aplikasi mobile) tidak boleh menerima detail internal.
        // Saat APP_DEBUG menyala, pesan bawaan Laravel memuat query SQL lengkap
        // beserta nilainya — pernah membuat hash password pendaftar tampil di
        // layar aplikasi. Balasan untuk /api/* selalu diseragamkan.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*')) {
                return null; // biarkan halaman web memakai penanganan bawaan
            }

            // Kesalahan yang memang ditujukan ke pengguna tetap apa adanya.
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpExceptionInterface) {
                return null;
            }

            // Kode pendek supaya kejadian di perangkat pengguna tetap bisa
            // ditelusuri di storage/logs/laravel.log tanpa membocorkan apa pun.
            $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            report(new RuntimeException("[REF {$ref}] {$e->getMessage()}", 0, $e));

            return response()->json([
                'success' => false,
                'message' => "Terjadi kesalahan di server. Sebutkan kode {$ref} saat menghubungi admin.",
                'reference' => $ref,
            ], 500);
        });
    })->create();
