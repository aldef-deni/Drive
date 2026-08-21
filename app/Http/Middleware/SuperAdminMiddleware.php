<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya superadministrator yang boleh lewat.
 *
 * Dipakai untuk pengelolaan perusahaan: admin perusahaan tidak boleh membuat,
 * mengubah, atau menghapus perusahaan mana pun — termasuk perusahaannya sendiri.
 */
class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Belum login.'], 401);
            }

            return redirect('/login');
        }

        if (!$user->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akses ditolak. Hanya superadministrator yang diizinkan.',
                ], 403);
            }

            abort(403, 'Akses ditolak. Hanya superadministrator yang diizinkan.');
        }

        return $next($request);
    }
}
