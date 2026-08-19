<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\File;
use App\Models\FileFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function __construct()
    {
        //
    }

    /**
     * Show admin dashboard.
     */
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'total_storage_used' => User::sum('storage_used'),
            'total_files' => \App\Models\File::count(),
        ];

        $users = User::withCount('files')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.dashboard', [
            'stats' => $stats,
            'users' => $users,
        ]);
    }

    /**
     * Show user management page.
     */
    public function users()
    {
        $users = User::withCount('files')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.users', [
            'users' => $users,
        ]);
    }

    /**
     * Edit user.
     */
    public function editUser(User $user)
    {
        return view('admin.edit-user', [
            'user' => $user,
        ]);
    }

    /**
     * Update user.
     */
    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,user',
            'storage_quota' => 'required|integer|min:10485760', // 10MB minimum
            'is_active' => 'required|boolean',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'storage_quota' => $request->storage_quota,
            'is_active' => $request->is_active,
        ]);

        return redirect()->route('admin.users')
            ->with('success', 'User berhasil diperbarui');
    }

    /**
     * Delete user.
     */
    public function deleteUser(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Tidak bisa menghapus akun sendiri');
        }

        // Delete user files
        foreach ($user->files as $file) {
            $fullPath = storage_path('app/drive/' . $file->path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $user->delete();

        return redirect()->route('admin.users')
            ->with('success', 'User berhasil dihapus');
    }

    /**
     * Toggle user active status.
     */
    public function toggleUserStatus(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Tidak bisa menonaktifkan akun sendiri');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return back()->with('success', $user->is_active ? 'User diaktifkan' : 'User dinonaktifkan');
    }

    /**
     * Reset user storage.
     */
    public function resetStorage(User $user)
    {
        $user->update(['storage_used' => 0]);

        return back()->with('success', 'Storage user berhasil direset');
    }

    /**
     * Show lock management page.
     */
    public function lockManagement(Request $request)
    {
        $search = $request->get('search', '');
        $type = $request->get('type', 'all');

        $lockedFiles = File::where('lock_password', '!=', null)
            ->with('user');
        $lockedFolders = FileFolder::where('lock_password', '!=', null)
            ->with('user');

        if ($search) {
            $lockedFiles->where('original_name', 'like', "%{$search}%");
            $lockedFolders->where('name', 'like', "%{$search}%");
        }

        if ($type === 'files') {
            $lockedFolders = collect();
        } elseif ($type === 'folders') {
            $lockedFiles = collect();
        } else {
            $lockedFiles = $lockedFiles->get();
            $lockedFolders = $lockedFolders->get();
        }

        return view('admin.lock-management', [
            'lockedFiles' => $lockedFiles instanceof \Illuminate\Support\Collection ? $lockedFiles : $lockedFiles->get(),
            'lockedFolders' => $lockedFolders instanceof \Illuminate\Support\Collection ? $lockedFolders : $lockedFolders->get(),
            'search' => $search,
            'type' => $type,
            'totalLocked' => File::where('lock_password', '!=', null)->count() + FileFolder::where('lock_password', '!=', null)->count(),
        ]);
    }

    /**
     * Change file lock password (admin).
     */
    public function changeFileLockPassword(Request $request, File $file)
    {
        $request->validate([
            'new_password' => 'required|string|min:4',
        ]);

        $file->lock_password = Hash::make($request->new_password);
        $file->save();

        return back()->with('success', 'Lock password file "' . $file->original_name . '" berhasil diubah');
    }

    /**
     * Remove file lock (admin).
     */
    public function removeFileLock(File $file)
    {
        $file->lock_password = null;
        $file->save();

        return back()->with('success', 'Lock pada file "' . $file->original_name . '" berhasil dihapus');
    }

    /**
     * Change folder lock password (admin).
     */
    public function changeFolderLockPassword(Request $request, FileFolder $folder)
    {
        $request->validate([
            'new_password' => 'required|string|min:4',
        ]);

        $folder->lock_password = Hash::make($request->new_password);
        $folder->save();

        return back()->with('success', 'Lock password folder "' . $folder->name . '" berhasil diubah');
    }

    /**
     * Remove folder lock (admin).
     */
    public function removeFolderLock(FileFolder $folder)
    {
        $folder->lock_password = null;
        $folder->save();

        return back()->with('success', 'Lock pada folder "' . $folder->name . '" berhasil dihapus');
    }
}
