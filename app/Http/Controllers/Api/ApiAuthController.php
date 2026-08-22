<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Notification;
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
            'email' => 'required|string',
            'password' => 'required',
        ], [], ['email' => 'email atau username']);

        // Superadministrator masuk memakai username, pengguna lain memakai
        // email. Satu kolom isian menerima keduanya, sama seperti di web -
        // tanpa ini superadmin tidak bisa masuk lewat aplikasi sama sekali.
        $isian = trim($request->email);
        $kolom = filter_var($isian, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($kolom, $isian)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/username atau password salah',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda belum diverifikasi oleh admin',
            ], 403);
        }

        if ($user->company && !$user->company->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Perusahaan Anda sedang nonaktif. Hubungi administrator.',
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
                'role_label' => $user->roleLabel(),
                'is_superadmin' => $user->isSuperAdmin(),
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'logo' => $user->company->logoUrl(),
                ] : null,
                'avatar' => $user->avatarUrl(),
                'storage_quota' => $user->storage_quota,
                'storage_used' => $user->storage_used,
                'storage_percentage' => $user->getStoragePercentage(),
                'is_active' => $user->is_active,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'company_id' => 'required|exists:companies,id',
        ]);

        // Tanpa perusahaan, akun hasil pendaftaran menggantung dan tidak
        // terlihat oleh admin mana pun.
        $company = Company::where('id', $request->company_id)->where('is_active', true)->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Perusahaan tidak tersedia atau sedang nonaktif.',
            ], 422);
        }

        if ($company->isFull()) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah akun di perusahaan ini sudah penuh. Hubungi admin Anda.',
            ], 422);
        }

        $user = User::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => User::ROLE_USER,
            'storage_quota' => $company->default_quota,
            'storage_used' => 0,
            'is_active' => false,
            'api_token' => Str::random(64),
        ]);

        // Beri tahu admin, sama seperti registrasi lewat web. Tanpa ini
        // pendaftar dari aplikasi bisa menunggu tanpa ada yang tahu.
        $penerima = User::where('is_active', true)
            ->where(function ($q) use ($company) {
                $q->where(fn ($w) => $w->where('role', User::ROLE_ADMIN)->where('company_id', $company->id))
                    ->orWhere('role', User::ROLE_SUPERADMIN);
            })
            ->get();

        foreach ($penerima as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type'    => 'new_registration',
                'title'   => 'Pengguna Baru Mendaftar',
                'message' => $user->name . ' (' . $user->email . ') mendaftar lewat aplikasi dan menunggu aktivasi.',
                'icon'    => 'fas fa-user-plus',
                'color'   => 'blue',
                'url'     => '/admin/users',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Mohon tunggu verifikasi admin.',
        ]);
    }

    /**
     * Daftar perusahaan aktif untuk pemilihan saat registrasi.
     * Publik, dan sengaja hanya memuat id serta nama.
     */
    public function companies()
    {
        return response()->json([
            'success' => true,
            'companies' => Company::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'logo' => $c->logoUrl(),
                    'quota_gb' => $c->defaultQuotaGb(),
                ]),
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
                'role_label' => $user->roleLabel(),
                'is_superadmin' => $user->isSuperAdmin(),
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'logo' => $user->company->logoUrl(),
                ] : null,
                'avatar' => $user->avatarUrl(),
                'storage_quota' => $user->storage_quota,
                'storage_used' => $user->storage_used,
                'storage_percentage' => $user->getStoragePercentage(),
                'is_active' => $user->is_active,
                'unread_notifications' => $user->unreadNotificationsCount(),
            ],
        ]);
    }
}
