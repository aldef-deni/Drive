<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            'pending_users' => User::where('is_active', false)->count(),
            'total_storage_used' => User::sum('storage_used'),
            'total_files' => \App\Models\File::count(),
        ];

        // Akun yang baru mendaftar dan masih menunggu verifikasi admin.
        $pendingUsers = User::where('is_active', false)
            ->orderBy('created_at', 'desc')
            ->get();

        $users = User::withCount('files')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.dashboard', [
            'stats' => $stats,
            'pendingUsers' => $pendingUsers,
            'users' => $users,
        ]);
    }

    /**
     * Show user management page.
     */
    public function users(Request $request)
    {
        // Filter: semua / menunggu verifikasi / aktif
        $filter = $request->get('filter', 'all');

        $users = User::withCount('files')
            ->when($filter === 'pending', fn ($q) => $q->where('is_active', false))
            ->when($filter === 'active', fn ($q) => $q->where('is_active', true))
            ->orderByRaw('is_active asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'filter' => $filter,
            'pendingCount' => User::where('is_active', false)->count(),
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
            'is_active' => 'nullable|boolean',
        ]);

        // Checkbox yang tidak dicentang tidak ikut terkirim — anggap non-aktif.
        $isActive = $request->boolean('is_active');

        if ($user->id === Auth::id() && (!$isActive || $request->role !== 'admin')) {
            return back()
                ->withInput()
                ->with('error', 'Tidak bisa menurunkan role atau menonaktifkan akun sendiri');
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'storage_quota' => $request->storage_quota,
            'is_active' => $isActive,
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

        // Delete avatar
        if ($user->avatar) {
            $avatarPath = storage_path('app/public/avatars/' . $user->avatar);
            if (file_exists($avatarPath)) {
                unlink($avatarPath);
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

        if ($user->is_active) {
            Notification::create([
                'user_id' => $user->id,
                'type'    => 'account_activated',
                'title'   => 'Akun Diverifikasi',
                'message' => 'Akun Anda telah diverifikasi admin. Selamat menggunakan Dekorasi Drive.',
                'icon'    => 'fas fa-circle-check',
                'color'   => 'green',
                'url'     => '/drive',
            ]);
        }

        return back()->with('success', $user->is_active
            ? 'Akun ' . $user->name . ' berhasil diverifikasi dan diaktifkan'
            : 'Akun ' . $user->name . ' dinonaktifkan');
    }

    /**
     * Halaman pengaturan kata kunci rahasia (Hidden System).
     */
    public function hiddenSystem()
    {
        return view('admin.hidden-system', [
            'isDefault' => Setting::get(Setting::HIDDEN_KEYWORD) === null,
            'updatedAt' => Setting::hiddenKeywordUpdatedAt(),
        ]);
    }

    /**
     * Ganti kata kunci rahasia untuk memunculkan file/folder tersembunyi.
     */
    public function updateHiddenKeyword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'keyword' => ['required', 'string', 'min:4', 'max:64', 'confirmed', 'regex:/^\S+$/u'],
        ], [
            'keyword.regex' => 'Kata kunci tidak boleh mengandung spasi.',
        ], [
            'current_password' => 'password admin',
            'keyword' => 'kata kunci baru',
        ]);

        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return back()->withErrors(['current_password' => 'Password admin salah.']);
        }

        Setting::setHiddenKeyword($request->keyword);

        Notification::create([
            'user_id' => Auth::id(),
            'type'    => 'hidden_keyword_updated',
            'title'   => 'Kata Kunci Rahasia Diubah',
            'message' => 'Kata kunci untuk memunculkan file tersembunyi berhasil diperbarui.',
            'icon'    => 'fas fa-user-secret',
            'color'   => 'purple',
            'url'     => null,
        ]);

        return redirect()->route('admin.hidden')
            ->with('success', 'Kata kunci rahasia berhasil diperbarui');
    }

    /**
     * Reset user storage.
     */
    public function resetStorage(User $user, \App\Services\StorageService $storageService)
    {
        // Hitung ulang dari file yang benar-benar ada, bukan sekadar dinolkan.
        $storageService->recalculateStorage($user);

        return back()->with('success', 'Pemakaian storage user berhasil dihitung ulang');
    }

}
