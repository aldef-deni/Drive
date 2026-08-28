<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\File;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiAdminController extends Controller
{
    /**
     * Query pengguna yang sudah dibatasi ke perusahaan pelaku.
     * Superadministrator memperoleh seluruh data tanpa penyaringan.
     */
    private function lingkup()
    {
        return User::query()->visibleTo(request()->user());
    }

    /**
     * Tolak bila akun berada di luar wewenang pelaku.
     */
    private function pastikanBerhak(User $user)
    {
        if (request()->user()->canManage($user)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak. Akun ini berada di luar perusahaan Anda.',
        ], 403);
    }

    public function dashboard()
    {
        $totalUsers = $this->lingkup()->count();
        $activeUsers = $this->lingkup()->where('is_active', true)->count();
        $pendingUsers = $this->lingkup()->where('is_active', false)->count();
        $totalFiles = File::whereIn('user_id', $this->lingkup()->select('id'))->count();
        $totalStorage = $this->lingkup()->sum('storage_used');

        $recentUsers = $this->lingkup()->orderBy('created_at', 'desc')->limit(5)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => $u->is_active,
                'created_at' => $u->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'stats' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'pending_users' => $pendingUsers,
                'total_files' => $totalFiles,
                'total_storage' => $totalStorage,
                'total_storage_formatted' => User::formatStorageSize($totalStorage),
            ],
            'recent_users' => $recentUsers,
        ]);
    }

    public function users()
    {
        // with('company'): tanpa ini nama perusahaan memicu satu query per baris.
        $users = $this->lingkup()->with('company')->orderBy('created_at', 'desc')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'role_label' => $u->roleLabel(),
                // Superadmin melihat lintas perusahaan, jadi tanpa penanda ini
                // dua nama yang mirip dari perusahaan berbeda tak terbedakan.
                'company' => $u->company?->name,
                'avatar' => $u->avatarUrl(),
                'storage_quota' => $u->storage_quota,
                'storage_quota_gb' => round($u->storage_quota / 1073741824, 2),
                'storage_used' => $u->storage_used,
                'storage_used_formatted' => User::formatStorageSize($u->storage_used),
                'storage_percentage' => $u->getStoragePercentage(),
                'is_active' => $u->is_active,
                'created_at' => $u->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }

    public function getUser(User $user)
    {
        if ($tolak = $this->pastikanBerhak($user)) {
            return $tolak;
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatarUrl(),
                'storage_quota' => $user->storage_quota,
                'storage_quota_gb' => round($user->storage_quota / 1073741824, 2),
                'storage_used' => $user->storage_used,
                'storage_used_formatted' => User::formatStorageSize($user->storage_used),
                'is_active' => $user->is_active,
                'created_at' => $user->created_at->toISOString(),
            ],
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        if ($tolak = $this->pastikanBerhak($user)) {
            return $tolak;
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:user,admin',
            // Batas bawah disamakan dengan dasbor web (10 MB). Sebelumnya min:1
            // menolak kuota di bawah 1 GB, sehingga akun yang diatur dari web
            // tidak bisa disimpan ulang lewat aplikasi.
            'storage_quota_gb' => 'sometimes|numeric|min:0.01',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($request->has('name')) $user->name = $request->name;
        if ($request->has('email')) $user->email = $request->email;
        if ($request->has('role')) $user->role = $request->role;
        if ($request->has('storage_quota_gb')) $user->storage_quota = $request->storage_quota_gb * 1073741824;
        if ($request->has('is_active')) $user->is_active = $request->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diupdate',
        ]);
    }

    public function deleteUser(User $user)
    {
        if ($tolak = $this->pastikanBerhak($user)) {
            return $tolak;
        }

        if ($user->role === 'admin') {
            return response()->json(['success' => false, 'message' => 'Tidak bisa menghapus admin'], 400);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus',
        ]);
    }

    public function toggleStatus(User $user)
    {
        if ($tolak = $this->pastikanBerhak($user)) {
            return $tolak;
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'success' => true,
            'message' => "User berhasil $status",
            'is_active' => $user->is_active,
        ]);
    }

    public function resetStorage(User $user)
    {
        if ($tolak = $this->pastikanBerhak($user)) {
            return $tolak;
        }

        $user->storage_used = File::where('user_id', $user->id)->sum('size');
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Storage berhasil di-reset',
            'storage_used' => $user->storage_used,
            'storage_used_formatted' => User::formatStorageSize($user->storage_used),
        ]);
    }

    /**
     * Status kata kunci rahasia (nilainya sendiri tidak pernah dikirim).
     */
    public function hiddenKeyword()
    {
        // Kata kuncinya hanya diperlihatkan kepada superadministrator, sama
        // seperti di dasbor web. Admin perusahaan tetap boleh menggantinya.
        $terlihat = request()->user()->isSuperAdmin();

        return response()->json([
            'success' => true,
            'is_default' => Setting::bacaLangsung(Setting::HIDDEN_KEYWORD) === null,
            'updated_at' => Setting::hiddenKeywordUpdatedAt()?->toISOString(),
            'can_reveal' => $terlihat,
            'keyword' => $terlihat ? Setting::hiddenKeywordPlain() : null,
            'state' => Setting::hiddenKeywordState(),
        ]);
    }

    /**
     * Ganti kata kunci rahasia untuk memunculkan file/folder tersembunyi.
     */
    public function updateHiddenKeyword(Request $request)
    {
        // Sama seperti di web: kata kunci ini berlaku lintas perusahaan.
        if ($request->user()->isDemo()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun demo tidak bisa mengganti kata kunci rahasia.',
            ], 403);
        }

        $request->validate([
            'current_password' => ['required', 'string'],
            'keyword' => ['required', 'string', 'min:4', 'max:64', 'regex:/^\S+$/u'],
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password admin salah',
            ], 400);
        }

        Setting::setHiddenKeyword($request->keyword);

        return response()->json([
            'success' => true,
            'message' => 'Kata kunci rahasia berhasil diperbarui',
        ]);
    }
}
