<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;

class ProfileController extends Controller
{
    /**
     * Show profile page.
     */
    public function show()
    {
        $user = Auth::user();
        return view('profile.show', compact('user'));
    }

    /**
     * Update profile (name, email).
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type'    => 'profile_updated',
            'title'   => 'Profil Diperbarui',
            'message' => 'Profil Anda berhasil diperbarui.',
            'icon'    => 'fas fa-user-check',
            'color'   => 'green',
            'url'     => null,
        ]);

        // Beri tahu admin perusahaan yang sama saja, plus superadministrator.
        \App\Models\User::pengawasUntuk($user)->each(function ($admin) use ($user) {
            \App\Models\Notification::create([
                'user_id' => $admin->id,
                'type'    => 'profile_updated',
                'title'   => 'Profil Diperbarui',
                'message' => $user->name . ' memperbarui profilnya.',
                'icon'    => 'fas fa-user-edit',
                'color'   => 'blue',
                'url'     => '/admin/users/' . $user->id . '/edit',
            ]);
        });

        return back()->with('success', 'Profil berhasil diperbarui');
    }

    /**
     * Update password.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah']);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        \App\Models\Notification::create([
            'user_id' => $user->id,
            'type'    => 'profile_updated',
            'title'   => 'Password Diubah',
            'message' => 'Password akun Anda berhasil diubah.',
            'icon'    => 'fas fa-key',
            'color'   => 'green',
            'url'     => null,
        ]);

        return back()->with('success', 'Password berhasil diubah');
    }

    /**
     * Update avatar.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $user = Auth::user();

        // Delete old avatar
        $oldPath = storage_path('app/public/avatars/' . $user->avatar);
        if ($user->avatar && File::exists($oldPath)) {
            File::delete($oldPath);
        }

        // Store new avatar
        $file = $request->file('avatar');
        $filename = $user->id . '_' . time() . '.' . strtolower($file->getClientOriginalExtension());

        $directory = storage_path('app/public/avatars');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file->move($directory, $filename);

        $user->update(['avatar' => $filename]);

        return back()->with('success', 'Avatar berhasil diubah');
    }

    /**
     * Sajikan file avatar.
     *
     * Publik seperti halnya file di public/storage sebelumnya, tetapi tidak
     * bergantung pada symlink sehingga tetap jalan di cPanel.
     */
    public function avatar(\App\Models\User $user)
    {
        $path = $user->avatarPath();

        abort_if($path === null, 404);

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Hapus avatar.
     */
    public function destroyAvatar()
    {
        $user = Auth::user();

        if (!$user->avatar) {
            return back()->with('error', 'Tidak ada avatar untuk dihapus');
        }

        $path = storage_path('app/public/avatars/' . $user->avatar);
        if (File::exists($path)) {
            File::delete($path);
        }

        $user->update(['avatar' => null]);

        return back()->with('success', 'Avatar berhasil dihapus');
    }
}
