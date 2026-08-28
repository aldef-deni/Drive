<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
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
     * Pastikan pelaku berhak mengelola akun ini.
     *
     * Tanpa pemeriksaan ini, admin perusahaan A cukup menebak id untuk mengubah
     * atau menghapus akun di perusahaan B — route model binding tidak peduli
     * siapa pemiliknya.
     */
    private function pastikanBerhak(User $user): void
    {
        abort_unless(Auth::user()->canManage($user), 403,
            'Akses ditolak. Akun ini berada di luar perusahaan Anda.');
    }

    /**
     * Show admin dashboard.
     */
    public function index()
    {
        $pelaku = Auth::user();

        // Seluruh angka dihitung dari query yang sudah dibatasi cakupannya,
        // supaya admin perusahaan tidak pernah melihat data perusahaan lain.
        $lingkup = fn () => User::query()->visibleTo($pelaku);

        $stats = [
            'total_users' => $lingkup()->count(),
            'active_users' => $lingkup()->where('is_active', true)->count(),
            'pending_users' => $lingkup()->where('is_active', false)->count(),
            'total_storage_used' => $lingkup()->sum('storage_used'),
            'total_files' => \App\Models\File::whereIn('user_id', $lingkup()->select('id'))->count(),
        ];

        // Akun yang baru mendaftar dan masih menunggu verifikasi admin.
        $pendingUsers = $lingkup()
            ->where('is_active', false)
            ->with('company')
            ->orderBy('created_at', 'desc')
            ->get();

        $users = $lingkup()
            ->withCount('files')
            ->with('company')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.dashboard', [
            'stats' => $stats,
            'pendingUsers' => $pendingUsers,
            'users' => $users,
            'companyStats' => $pelaku->isSuperAdmin() ? [
                'total' => Company::count(),
                'active' => Company::where('is_active', true)->count(),
            ] : null,
        ]);
    }

    /**
     * Show user management page.
     */
    public function users(Request $request)
    {
        $pelaku = Auth::user();

        // Filter: semua / menunggu verifikasi / aktif
        $filter = $request->get('filter', 'all');

        // Superadmin boleh menyaring per perusahaan; admin selalu terkunci
        // pada perusahaannya sendiri.
        $companyId = $pelaku->isSuperAdmin() ? $request->get('company') : null;

        $users = User::query()
            ->visibleTo($pelaku)
            ->withCount('files')
            ->with('company')
            ->when($filter === 'pending', fn ($q) => $q->where('is_active', false))
            ->when($filter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByRaw('is_active asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'filter' => $filter,
            'companyId' => $companyId,
            'companies' => $pelaku->isSuperAdmin() ? Company::orderBy('name')->get() : collect(),
            'pendingCount' => User::query()->visibleTo($pelaku)->where('is_active', false)->count(),
        ]);
    }

    /**
     * Edit user.
     */
    public function editUser(User $user)
    {
        $this->pastikanBerhak($user);

        return view('admin.edit-user', [
            'user' => $user,
        ]);
    }

    /**
     * Update user.
     */
    public function updateUser(Request $request, User $user)
    {
        $this->pastikanBerhak($user);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => ['required', Auth::user()->isSuperAdmin()
                ? 'in:superadmin,admin,user'
                : 'in:admin,user'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'storage_quota' => 'required|integer|min:10485760', // 10MB minimum
            'is_active' => 'nullable|boolean',
        ]);

        // Checkbox yang tidak dicentang tidak ikut terkirim — anggap non-aktif.
        $isActive = $request->boolean('is_active');

        // Dibandingkan dengan peran yang berlaku sekarang, bukan dengan 'admin'.
        // Sebelumnya superadministrator yang menyunting akunnya sendiri selalu
        // tertolak, karena perannya memang bukan 'admin'.
        if ($user->id === Auth::id() && (!$isActive || $request->role !== $user->role)) {
            return back()
                ->withInput()
                ->with('error', 'Tidak bisa mengubah peran atau menonaktifkan akun sendiri');
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'storage_quota' => $request->storage_quota,
            'is_active' => $isActive,
        ];

        // Memindahkan akun antar perusahaan hanya wewenang superadministrator.
        if (Auth::user()->isSuperAdmin() && $request->filled('company_id')) {
            $data['company_id'] = (int) $request->company_id;
        }

        $user->update($data);

        return redirect()->route('admin.users')
            ->with('success', 'User berhasil diperbarui');
    }

    /**
     * Delete user.
     */
    public function deleteUser(User $user)
    {
        $this->pastikanBerhak($user);

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
        $this->pastikanBerhak($user);

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
                'message' => 'Akun Anda telah diverifikasi admin. Selamat menggunakan Aldef Tech Drive.',
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
    /**
     * Formulir pembuatan akun oleh superadministrator.
     */
    public function createUser()
    {
        $pelaku = Auth::user();

        // Admin perusahaan hanya boleh menambah ke perusahaannya sendiri, jadi
        // daftar pilihannya pun hanya berisi satu itu.
        $companies = Company::where('is_active', true)
            ->when(!$pelaku->isSuperAdmin(), fn ($q) => $q->where('id', $pelaku->company_id))
            ->orderBy('name')
            ->get();

        if ($companies->isEmpty()) {
            return redirect()->route('admin.users')->with(
                'error',
                $pelaku->isSuperAdmin()
                    ? 'Belum ada perusahaan aktif. Tambahkan perusahaan terlebih dahulu.'
                    : 'Perusahaan Anda sedang nonaktif, akun baru tidak bisa dibuat.'
            );
        }

        return view('admin.create-user', [
            'companies' => $companies,
            'kunciPerusahaan' => !$pelaku->isSuperAdmin(),
        ]);
    }

    /**
     * Simpan akun buatan superadministrator.
     *
     * Akun langsung aktif: verifikasi ada untuk menyaring pendaftar dari luar,
     * sedangkan akun ini dibuat oleh orang yang berwenang menyetujuinya.
     */
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company_id' => ['required', 'exists:companies,id'],
            // Superadministrator tidak bisa dibuat lewat formulir - satu-satunya
            // jalan adalah migrasi, supaya peran tertinggi tidak bisa diperbanyak.
            'role' => ['required', 'in:' . User::ROLE_ADMIN . ',' . User::ROLE_USER],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], [
            'company_id' => 'perusahaan',
            'role' => 'peran',
        ]);

        $pelaku = Auth::user();

        // Perusahaan yang dikirim tidak dipercaya begitu saja: admin
        // perusahaan selalu dikunci ke perusahaannya sendiri, apa pun isi
        // formulir yang sampai ke server.
        $company = $pelaku->isSuperAdmin()
            ? Company::find($request->company_id)
            : $pelaku->company;

        if (!$company) {
            return back()->withInput()
                ->withErrors(['company_id' => 'Perusahaan tidak ditemukan.']);
        }

        if (!$company->is_active) {
            return back()->withInput()
                ->withErrors(['company_id' => 'Perusahaan ini sedang nonaktif.']);
        }

        if ($company->isFull()) {
            return back()->withInput()
                ->withErrors(['company_id' => 'Jumlah akun di perusahaan ini sudah mencapai batas maksimal.']);
        }

        $user = User::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'storage_quota' => $company->default_quota,
            'is_active' => true,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'account_created',
            'title'   => 'Akun Anda Sudah Aktif',
            'message' => 'Akun Anda dibuat oleh administrator di ' . $company->name
                . ' dengan kuota ' . User::formatStorageSize($company->default_quota) . '.',
            'icon'    => 'fas fa-circle-check',
            'color'   => 'green',
            'url'     => null,
        ]);

        return redirect()->route('admin.users')
            ->with('success', 'Akun ' . $user->name . ' (' . $user->roleLabel() . ') berhasil dibuat dan langsung aktif');
    }

    public function hiddenSystem()
    {
        // Kata kunci hanya diperlihatkan kepada superadministrator. Admin
        // perusahaan tetap bisa menggantinya, tetapi tidak melihat nilainya.
        $terlihat = Auth::user()->isSuperAdmin();

        return view('admin.hidden-system', [
            'isDefault' => Setting::bacaLangsung(Setting::HIDDEN_KEYWORD) === null,
            'updatedAt' => Setting::hiddenKeywordUpdatedAt(),
            'keyword' => $terlihat ? Setting::hiddenKeywordPlain() : null,
            'keywordState' => Setting::hiddenKeywordState(),
            'canReveal' => $terlihat,
        ]);
    }


    /**
     * Ganti kata kunci rahasia untuk memunculkan file/folder tersembunyi.
     */
    public function updateHiddenKeyword(Request $request)
    {
        // Kata kunci ini berlaku untuk SELURUH perusahaan, jadi ia satu-satunya
        // pengaturan admin yang dampaknya keluar dari perusahaan sendiri. Akun
        // demo dipakai orang asing - membiarkannya mengganti kata kunci berarti
        // seluruh pelanggan kehilangan akses ke file tersembunyi mereka.
        if (Auth::user()->isDemo()) {
            return back()->with('error',
                'Akun demo tidak bisa mengganti kata kunci rahasia, karena kata kunci ini berlaku untuk semua perusahaan.');
        }

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
        $this->pastikanBerhak($user);

        // Hitung ulang dari file yang benar-benar ada, bukan sekadar dinolkan.
        $storageService->recalculateStorage($user);

        return back()->with('success', 'Pemakaian storage user berhasil dihitung ulang');
    }

}
