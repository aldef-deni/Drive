<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ApiProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatarUrl(),
                'storage_quota' => $user->storage_quota,
                'storage_used' => $user->storage_used,
                'storage_percentage' => $user->getStoragePercentage(),
                'created_at' => $user->created_at->toISOString(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'profile_updated',
            'title' => 'Profile Diperbarui',
            'message' => 'Profile Anda berhasil diperbarui.',
            'icon' => 'fas fa-user-check',
            'color' => 'green',
            'url' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile berhasil diperbarui',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama salah',
            ], 400);
        }

        $user->password = $request->password;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah',
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $user = $request->user();

        // Konvensi penyimpanan harus sama persis dengan versi web: file fisik di
        // storage/app/public/avatars dan kolom `avatar` berisi nama file polos.
        // Sebelumnya API menyimpan "avatars/xxx.png" sehingga avatar yang
        // diunggah dari mobile tidak pernah tampil di web, dan sebaliknya.
        if ($old = $user->avatarPath()) {
            @unlink($old);
        }

        $file = $request->file('avatar');
        $filename = $user->id . '_' . time() . '.' . strtolower($file->getClientOriginalExtension());

        $directory = storage_path('app/public/avatars');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file->move($directory, $filename);

        $user->avatar = $filename;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Avatar berhasil diubah',
            'avatar_url' => $user->fresh()->avatarUrl(),
        ]);
    }
}
