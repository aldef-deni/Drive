<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Pakai user dari request agar guard API (token) ikut terbaca, bukan hanya guard web.
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Belum login.'], 401);
            }

            return redirect('/login');
        }

        if (!$user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akses ditolak. Hanya admin yang diizinkan.'], 403);
            }

            abort(403, 'Akses ditolak. Hanya admin yang diizinkan.');
        }

        return $next($request);
    }
}
