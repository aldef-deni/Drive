<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ApiAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda belum diverifikasi oleh admin',
            ], 403);
        }

        // Generate or reuse API token
        if (!$user->api_token) {
            $user->api_token = Str::random(64);
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'token' => $user->api_token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'storage_quota' => $user->storage_quota,
                'storage_used' => $user->storage_used,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'user',
            'storage_quota' => 10737418240, // 10GB
            'storage_used' => 0,
            'is_active' => false,
            'api_token' => Str::random(64),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Mohon tunggu verifikasi admin.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->api_token = null;
        $request->user()->save();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
                'storage_quota' => $user->storage_quota,
                'storage_used' => $user->storage_used,
                'is_active' => $user->is_active,
                'unread_notifications' => $user->unreadNotificationsCount(),
            ],
        ]);
    }
}
