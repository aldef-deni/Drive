<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Pengelolaan kuota penyimpanan seluruh pengguna.
 *
 * Khusus superadministrator — pembatasannya dipasang lewat middleware
 * `superadmin` pada grup route. Admin perusahaan tetap bisa mengatur kuota
 * penggunanya sendiri lewat Manajemen User; halaman ini untuk pandangan
 * menyeluruh lintas perusahaan.
 */
class QuotaController extends Controller
{
    private const GB = 1073741824;

    /** Kuota terkecil yang masuk akal: 10 MB. */
    private const MIN_QUOTA = 10485760;

    public function index(Request $request)
    {
        $cari = trim((string) $request->get('search', ''));
        $companyId = $request->get('company');
        $urut = $request->get('sort', 'usage');

        $users = User::query()
            ->with('company')
            ->withCount('files')
            ->when($cari !== '', fn ($q) => $q->where(function ($w) use ($cari) {
                $w->where('name', 'like', '%' . $cari . '%')
                    ->orWhere('email', 'like', '%' . $cari . '%');
            }))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            // "Paling penuh" lebih berguna daripada urutan abjad: yang hampir
            // kehabisan kuota itulah yang perlu ditindak lebih dulu.
            ->when($urut === 'usage',
                fn ($q) => $q->orderByRaw('CASE WHEN storage_quota > 0 THEN storage_used / storage_quota ELSE 0 END DESC'))
            ->when($urut === 'quota', fn ($q) => $q->orderBy('storage_quota', 'desc'))
            ->when($urut === 'name', fn ($q) => $q->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        $terpakai = (int) User::sum('storage_used');
        $dialokasikan = (int) User::sum('storage_quota');

        return view('admin.quotas.index', [
            'users' => $users,
            'companies' => Company::orderBy('name')->get(),
            'search' => $cari,
            'companyId' => $companyId,
            'sort' => $urut,
            'stats' => [
                'allocated' => $dialokasikan,
                'used' => $terpakai,
                'percent' => $dialokasikan > 0 ? ($terpakai / $dialokasikan) * 100 : 0,
                'near_full' => User::whereRaw('storage_quota > 0 AND storage_used / storage_quota >= 0.9')->count(),
            ],
        ]);
    }

    /**
     * Ubah kuota satu pengguna.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'quota_gb' => ['required', 'numeric', 'min:0.01', 'max:10240'],
        ], [], ['quota_gb' => 'kuota']);

        $baru = max(self::MIN_QUOTA, (int) round($request->quota_gb * self::GB));
        $lama = $user->storage_quota;

        $user->update(['storage_quota' => $baru]);

        $this->beriTahu($user, $lama, $baru);

        $pesan = 'Kuota ' . $user->name . ' diubah menjadi ' . User::formatStorageSize($baru);

        // Kuota di bawah pemakaian bukan kesalahan, tapi pengguna langsung tidak
        // bisa mengunggah apa pun — itu perlu dikatakan, bukan didiamkan.
        if ($baru < $user->storage_used) {
            $pesan .= '. Perhatian: kuota ini di bawah pemakaian saat ini ('
                . User::formatStorageSize($user->storage_used)
                . '), sehingga pengguna tidak bisa mengunggah file baru.';
        }

        return back()->with('success', $pesan);
    }

    /**
     * Terapkan satu nilai kuota ke banyak pengguna sekaligus.
     */
    public function bulk(Request $request)
    {
        $request->validate([
            'quota_gb' => ['required', 'numeric', 'min:0.01', 'max:10240'],
            'target' => ['required', 'in:company,all'],
            'company_id' => ['required_if:target,company', 'nullable', 'exists:companies,id'],
        ], [], [
            'quota_gb' => 'kuota',
            'company_id' => 'perusahaan',
        ]);

        $baru = max(self::MIN_QUOTA, (int) round($request->quota_gb * self::GB));

        $query = User::query()
            ->when($request->target === 'company',
                fn ($q) => $q->where('company_id', $request->company_id));

        $pengguna = $query->get();

        if ($pengguna->isEmpty()) {
            return back()->with('error', 'Tidak ada pengguna yang cocok dengan pilihan tersebut.');
        }

        foreach ($pengguna as $u) {
            $lama = $u->storage_quota;

            if ($lama === $baru) {
                continue;
            }

            $u->update(['storage_quota' => $baru]);
            $this->beriTahu($u, $lama, $baru);
        }

        $lingkup = $request->target === 'company'
            ? Company::find($request->company_id)?->name ?? 'perusahaan terpilih'
            : 'seluruh perusahaan';

        return back()->with('success',
            'Kuota ' . User::formatStorageSize($baru) . ' diterapkan ke ' .
            $pengguna->count() . ' pengguna di ' . $lingkup);
    }

    /**
     * Terapkan kuota bawaan perusahaan ke seluruh penggunanya.
     */
    public function applyCompanyDefault(Company $company)
    {
        $pengguna = $company->users()->get();

        if ($pengguna->isEmpty()) {
            return back()->with('error', 'Perusahaan ' . $company->name . ' belum memiliki pengguna.');
        }

        $diubah = 0;

        foreach ($pengguna as $u) {
            if ($u->storage_quota === $company->default_quota) {
                continue;
            }

            $lama = $u->storage_quota;
            $u->update(['storage_quota' => $company->default_quota]);
            $this->beriTahu($u, $lama, $company->default_quota);
            $diubah++;
        }

        return back()->with('success', $diubah === 0
            ? 'Seluruh pengguna ' . $company->name . ' sudah memakai kuota bawaan perusahaan.'
            : $diubah . ' pengguna ' . $company->name . ' disesuaikan ke kuota bawaan '
                . User::formatStorageSize($company->default_quota));
    }

    /**
     * Beri tahu pengguna bahwa kuotanya berubah.
     *
     * Perubahan kuota terasa langsung oleh pengguna — terutama saat dikurangi —
     * jadi jangan sampai mereka mengetahuinya dari kegagalan unggah.
     */
    private function beriTahu(User $user, int $lama, int $baru): void
    {
        $naik = $baru > $lama;

        Notification::create([
            'user_id' => $user->id,
            'type' => 'quota_changed',
            'title' => $naik ? 'Kuota Ditambah' : 'Kuota Dikurangi',
            'message' => 'Kuota penyimpanan Anda diubah dari ' . User::formatStorageSize($lama)
                . ' menjadi ' . User::formatStorageSize($baru) . '.',
            'icon' => $naik ? 'fas fa-circle-up' : 'fas fa-circle-down',
            'color' => $naik ? 'green' : 'amber',
            'url' => null,
        ]);
    }
}
