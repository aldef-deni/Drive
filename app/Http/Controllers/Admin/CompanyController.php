<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Pengelolaan perusahaan penyewa.
 *
 * Seluruh aksi di sini khusus superadministrator; pembatasannya dipasang lewat
 * middleware `superadmin` pada grup route, bukan diperiksa satu per satu.
 */
class CompanyController extends Controller
{
    private const GB = 1073741824;

    public function index(Request $request)
    {
        $cari = trim((string) $request->get('search', ''));

        $companies = Company::withCount('users')
            ->when($cari !== '', fn ($q) => $q->where(function ($w) use ($cari) {
                $w->where('name', 'like', '%' . $cari . '%')
                    ->orWhere('email', 'like', '%' . $cari . '%')
                    ->orWhere('slug', 'like', '%' . $cari . '%');
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.companies.index', [
            'companies' => $companies,
            'search' => $cari,
            'stats' => [
                'total' => Company::count(),
                'active' => Company::where('is_active', true)->count(),
                'users' => User::whereNotNull('company_id')->count(),
                'storage' => User::whereNotNull('company_id')->sum('storage_used'),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.companies.form', [
            'company' => new Company(['default_quota' => User::DEFAULT_STORAGE_QUOTA, 'is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $company = Company::create($data);

        return redirect()->route('admin.companies.index')
            ->with('success', 'Perusahaan ' . $company->name . ' berhasil ditambahkan');
    }

    public function edit(Company $company)
    {
        return view('admin.companies.form', ['company' => $company]);
    }

    public function update(Request $request, Company $company)
    {
        $company->update($this->validated($request, $company));

        return redirect()->route('admin.companies.index')
            ->with('success', 'Perusahaan ' . $company->name . ' berhasil diperbarui');
    }

    public function toggle(Company $company)
    {
        $company->is_active = !$company->is_active;
        $company->save();

        // Menonaktifkan perusahaan ikut menutup akses seluruh penggunanya,
        // kalau tidak mereka masih bisa masuk seolah tidak terjadi apa-apa.
        $company->users()->update(['is_active' => $company->is_active]);

        return back()->with('success', $company->is_active
            ? 'Perusahaan ' . $company->name . ' diaktifkan beserta seluruh penggunanya'
            : 'Perusahaan ' . $company->name . ' dinonaktifkan beserta seluruh penggunanya');
    }

    public function destroy(Request $request, Company $company)
    {
        $jumlah = $company->users()->count();

        if ($jumlah > 0) {
            // Menghapus perusahaan berpenghuni akan membuat file penggunanya
            // menggantung tanpa pemilik. Wajib dikosongkan lebih dulu.
            return back()->with('error',
                'Perusahaan ' . $company->name . ' masih memiliki ' . $jumlah .
                ' pengguna. Pindahkan atau hapus penggunanya terlebih dahulu.');
        }

        $nama = $company->name;
        $company->delete();

        return redirect()->route('admin.companies.index')
            ->with('success', 'Perusahaan ' . $nama . ' berhasil dihapus');
    }

    /**
     * Form tambah admin untuk sebuah perusahaan.
     */
    public function createAdmin(Company $company)
    {
        return view('admin.companies.admin-form', ['company' => $company]);
    }

    public function storeAdmin(Request $request, Company $company)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($company->isFull()) {
            return back()->withInput()->with('error',
                'Jumlah akun di perusahaan ini sudah mencapai batas maksimal.');
        }

        User::create([
            'company_id' => $company->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_ADMIN,
            'storage_quota' => $company->default_quota,
            'is_active' => true,
        ]);

        return redirect()->route('admin.companies.index')
            ->with('success', 'Admin untuk ' . $company->name . ' berhasil dibuat');
    }

    /**
     * Aturan validasi bersama untuk tambah dan ubah.
     */
    private function validated(Request $request, ?Company $company = null): array
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
        ], [], [
            'name' => 'nama perusahaan',
            'default_quota_gb' => 'kuota bawaan',
            'max_users' => 'batas jumlah akun',
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
}
