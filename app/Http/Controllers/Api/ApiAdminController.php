<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiAdminController extends Controller
{
    public function dashboard()
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $pendingUsers = User::where('is_active', false)->count();
        $totalFiles = File::count();
        $totalStorage = User::sum('storage_used');

        $recentUsers = User::orderBy('created_at', 'desc')->limit(5)->get()->map(function ($u) {
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
        $users = User::orderBy('created_at', 'desc')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'avatar' => $u->avatar,
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
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => $user->avatar,
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
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:user,admin',
            'storage_quota_gb' => 'sometimes|numeric|min:1',
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
        $user->storage_used = File::where('user_id', $user->id)->sum('size');
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Storage berhasil di-reset',
            'storage_used' => $user->storage_used,
            'storage_used_formatted' => User::formatStorageSize($user->storage_used),
        ]);
    }
}
