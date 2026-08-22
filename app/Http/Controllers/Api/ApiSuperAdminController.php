<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Fungsi superadministrator untuk aplikasi.
 *
 * Cerminan menu Perusahaan, Kuota Penyimpanan, dan pembuatan akun di dasbor
 * web. Seluruh rute di sini dijaga middleware `superadmin`; tidak ada satu pun
 * yang boleh dijangkau admin perusahaan.
 */
class ApiSuperAdminController extends Controller
{
    private const GB = 1073741824;

    /** Kuota terkecil yang masuk akal, disamakan dengan dasbor web. */
    private const MIN_QUOTA = 10485760;

    // ------------------------------------------------------- Perusahaan

    public function companies()
    {
        $companies = Company::withCount('users')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'companies' => $companies->map(fn (Company $c) => $this->bentukPerusahaan($c)),
            'stats' => [
                'total' => $companies->count(),
                'active' => $companies->where('is_active', true)->count(),
                'users' => $companies->sum('users_count'),
            ],
        ]);
    }

    public function storeCompany(Request $request)
    {
        $company = Company::create($this->validasiPerusahaan($request));
        $this->simpanLogo($request, $company);

        return response()->json([
            'success' => true,
            'message' => 'Perusahaan ' . $company->name . ' berhasil ditambahkan',
            'company' => $this->bentukPerusahaan($company->loadCount('users')),
        ]);
    }

    public function updateCompany(Request $request, Company $company)
    {
        $company->update($this->validasiPerusahaan($request, $company));

        if ($request->boolean('hapus_logo')) {
            $this->hapusLogo($company);
        }

        $this->simpanLogo($request, $company);

        return response()->json([
            'success' => true,
            'message' => 'Perusahaan ' . $company->name . ' berhasil diperbarui',
            'company' => $this->bentukPerusahaan($company->fresh()->loadCount('users')),
        ]);
    }

    public function toggleCompany(Company $company)
    {
        $company->update(['is_active' => !$company->is_active]);

        // Menonaktifkan perusahaan harus ikut menutup akses penggunanya,
        // kalau tidak pemisahannya hanya di atas kertas.
        $company->users()->update(['is_active' => $company->is_active]);

        return response()->json([
            'success' => true,
            'message' => $company->name . ($company->is_active ? ' diaktifkan' : ' dinonaktifkan'),
            'company' => $this->bentukPerusahaan($company->loadCount('users')),
        ]);
    }

    public function destroyCompany(Company $company)
    {
        $jumlah = $company->users()->count();

        if ($jumlah > 0) {
            // Menghapus perusahaan berpenghuni membuat file penggunanya
            // menggantung tanpa pemilik.
            return response()->json([
                'success' => false,
                'message' => 'Perusahaan ini masih memiliki ' . $jumlah
                    . ' pengguna. Pindahkan atau hapus penggunanya terlebih dahulu.',
            ], 422);
        }

        $nama = $company->name;
        $this->hapusLogo($company);
        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perusahaan ' . $nama . ' berhasil dihapus',
        ]);
    }

    // ------------------------------------------------------------ Kuota

    public function quotas(Request $request)
    {
        $companyId = $request->get('company');

        $users = User::query()
            ->with('company')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'company' => $u->company?->name,
                'company_id' => $u->company_id,
                'storage_quota' => $u->storage_quota,
                'storage_quota_gb' => $this->gb($u->storage_quota),
                'storage_used' => $u->storage_used,
                'storage_used_formatted' => User::formatStorageSize($u->storage_used),
                'storage_percentage' => $u->getStoragePercentage(),
            ]),
            'companies' => Company::orderBy('name')->get()
                ->map(fn (Company $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'quota_gb' => $c->defaultQuotaGb(),
                ]),
        ]);
    }

    public function updateQuota(Request $request, User $user)
    {
        $request->validate([
            'quota_gb' => ['required', 'numeric', 'min:0.01', 'max:10240'],
        ], [], ['quota_gb' => 'kuota']);

        $lama = $user->storage_quota;
        $baru = max(self::MIN_QUOTA, (int) round($request->quota_gb * self::GB));

        $user->update(['storage_quota' => $baru]);
        $this->beriTahu($user, $lama, $baru);

        $pesan = 'Kuota ' . $user->name . ' menjadi ' . User::formatStorageSize($baru);

        if ($baru < $user->storage_used) {
            // Bukan kesalahan, tetapi pengguna langsung tidak bisa mengunggah
            // apa pun sampai ada yang dihapus - itu harus terlihat.
            $pesan .= ', di bawah pemakaian saat ini (' . User::formatStorageSize($user->storage_used) . ')';
        }

        return response()->json(['success' => true, 'message' => $pesan]);
    }

    public function bulkQuota(Request $request)
    {
        $request->validate([
            'quota_gb' => ['required', 'numeric', 'min:0.01', 'max:10240'],
            'target' => ['required', 'in:all,company'],
            'company_id' => ['required_if:target,company', 'nullable', 'exists:companies,id'],
        ], [], ['quota_gb' => 'kuota', 'company_id' => 'perusahaan']);

        $baru = max(self::MIN_QUOTA, (int) round($request->quota_gb * self::GB));

        $users = User::query()
            ->when($request->target === 'company', fn ($q) => $q->where('company_id', $request->company_id))
            ->get();

        foreach ($users as $user) {
            $lama = $user->storage_quota;
            $user->update(['storage_quota' => $baru]);
            $this->beriTahu($user, $lama, $baru);
        }

        return response()->json([
            'success' => true,
            'message' => $users->count() . ' akun diatur menjadi ' . User::formatStorageSize($baru),
        ]);
    }

    public function applyCompanyDefault(Company $company)
    {
        $users = $company->users()->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Perusahaan ini belum memiliki pengguna.',
            ], 422);
        }

        $diubah = 0;

        foreach ($users as $user) {
            if ($user->storage_quota === $company->default_quota) {
                continue; // sudah sesuai, tidak perlu diberi tahu ulang
            }

            $lama = $user->storage_quota;
            $user->update(['storage_quota' => $company->default_quota]);
            $this->beriTahu($user, $lama, $company->default_quota);
            $diubah++;
        }

        return response()->json([
            'success' => true,
            'message' => $diubah . ' akun disamakan dengan kuota bawaan ' . $company->name,
        ]);
    }

    // ------------------------------------------------------ Akun baru

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company_id' => ['required', 'exists:companies,id'],
            // Peran tertinggi hanya lahir dari migrasi; kalau bisa dibuat lewat
            // API, isolasi antar perusahaan kehilangan artinya.
            'role' => ['required', 'in:' . User::ROLE_ADMIN . ',' . User::ROLE_USER],
            'password' => ['required', 'string', 'min:8'],
        ], [], ['company_id' => 'perusahaan', 'role' => 'peran']);

        $company = Company::find($request->company_id);

        if (!$company->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Perusahaan ini sedang nonaktif.',
            ], 422);
        }

        if ($company->isFull()) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah akun di perusahaan ini sudah mencapai batas maksimal.',
            ], 422);
        }

        $user = User::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'storage_quota' => $company->default_quota,
            'storage_used' => 0,
            // Dibuat oleh orang yang berwenang menyetujuinya, jadi tidak perlu
            // antre verifikasi.
            'is_active' => true,
            'api_token' => Str::random(64),
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

        return response()->json([
            'success' => true,
            'message' => 'Akun ' . $user->name . ' (' . $user->roleLabel() . ') dibuat dan langsung aktif',
        ]);
    }

    // ------------------------------------------------------- Pembantu

    private function bentukPerusahaan(Company $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'email' => $c->email,
            'phone' => $c->phone,
            'address' => $c->address,
            'logo' => $c->logoUrl(),
            'default_quota_gb' => $c->defaultQuotaGb(),
            'max_users' => $c->max_users,
            'is_active' => $c->is_active,
            'users_count' => $c->users_count ?? $c->users()->count(),
            'storage_used_formatted' => User::formatStorageSize($c->storageUsed()),
        ];
    }

    private function validasiPerusahaan(Request $request, ?Company $company = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('companies', 'name')->ignore($company?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'default_quota_gb' => ['required', 'numeric', 'min:0.1', 'max:10240'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ], [], [
            'name' => 'nama perusahaan',
            'default_quota_gb' => 'kuota bawaan',
            'max_users' => 'batas jumlah akun',
            'logo' => 'logo perusahaan',
        ]);

        return [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'default_quota' => (int) round($data['default_quota_gb'] * self::GB),
            'max_users' => $data['max_users'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function simpanLogo(Request $request, Company $company): void
    {
        if (!$request->hasFile('logo')) {
            return;
        }

        $this->hapusLogo($company);

        $berkas = $request->file('logo');
        $nama = $company->id . '_' . time() . '.' . strtolower($berkas->getClientOriginalExtension());

        $direktori = storage_path(Company::LOGO_DIR);
        if (!is_dir($direktori)) {
            mkdir($direktori, 0755, true);
        }

        $berkas->move($direktori, $nama);
        $company->update(['logo' => $nama]);
    }

    private function hapusLogo(Company $company): void
    {
        if ($path = $company->logoPath()) {
            File::delete($path);
        }

        if ($company->logo) {
            $company->update(['logo' => null]);
        }
    }

    private function gb(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / self::GB, 2, '.', ''), '0'), '.');
    }

    private function beriTahu(User $user, int $lama, int $baru): void
    {
        if ($lama === $baru) {
            return;
        }

        $naik = $baru > $lama;

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'quota_changed',
            'title'   => $naik ? 'Kuota Ditambah' : 'Kuota Dikurangi',
            'message' => 'Kuota penyimpanan Anda kini ' . User::formatStorageSize($baru)
                . ' (sebelumnya ' . User::formatStorageSize($lama) . ').',
            'icon'    => $naik ? 'fas fa-circle-up' : 'fas fa-circle-down',
            'color'   => $naik ? 'green' : 'amber',
            'url'     => null,
        ]);
    }
}
