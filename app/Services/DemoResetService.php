<?php

namespace App\Services;

use App\Models\Company;
use App\Models\File;
use App\Models\FileFolder;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pemulihan akun demo ke keadaan semula.
 *
 * Demo dipakai orang asing yang boleh melakukan apa saja: mengunggah, menghapus,
 * mengganti password, bahkan menghapus akun lain di perusahaannya. Karena itu
 * pemulihannya membangun ulang seluruh isi perusahaan demo, bukan sekadar
 * menghapus berkas yang terlihat.
 */
class DemoResetService
{
    /** Kapan pemulihan terakhir dijalankan. */
    private const KUNCI_WAKTU = 'demo_last_reset';

    public function __construct(private StorageService $storageService)
    {
    }

    /** Apakah fitur demo dinyalakan? */
    public function aktif(): bool
    {
        return (string) config('demo.email') !== '';
    }

    /** Apakah alamat yang diketik di layar masuk itu akun demo? */
    public function cocokDenganDemo(?string $isian): bool
    {
        if (!$this->aktif() || !$isian) {
            return false;
        }

        return strcasecmp(trim($isian), (string) config('demo.email')) === 0;
    }

    /**
     * Pulihkan bila sudah waktunya.
     *
     * Sengaja dipanggil SEBELUM autentikasi. Kalau menunggu login berhasil,
     * pengunjung yang mengganti password akun demo akan mengunci semua orang
     * termasuk dirinya sendiri - pemulihannya tidak akan pernah terpicu.
     *
     * @return bool true bila pemulihan benar-benar dijalankan
     */
    public function pulihkanBilaPerlu(): bool
    {
        if (!$this->aktif() || !$this->sudahWaktunya()) {
            return false;
        }

        $this->pulihkan();

        return true;
    }

    private function sudahWaktunya(): bool
    {
        $terakhir = Setting::bacaLangsung(self::KUNCI_WAKTU);

        if (!$terakhir) {
            return true;
        }

        try {
            return Carbon::parse($terakhir)->addHours((int) config('demo.reset_after_hours'))->isPast();
        } catch (\Throwable $e) {
            // Nilai rusak diperlakukan sebagai belum pernah dipulihkan.
            return true;
        }
    }

    /**
     * Bangun ulang perusahaan demo dari nol.
     */
    public function pulihkan(): void
    {
        $company = $this->perusahaan();

        // Semua akun di perusahaan demo dibuang, termasuk yang dibuat pengunjung.
        // Akun demo utamanya dibangun ulang setelah ini.
        foreach ($company->users()->get() as $user) {
            $this->hapusBerkasFisik($user);
            File::where('user_id', $user->id)->delete();
            FileFolder::where('user_id', $user->id)->delete();
            Notification::where('user_id', $user->id)->delete();
            $user->delete();
        }

        $demo = $this->bangunAkun($company);
        $this->isiContoh($demo);

        $this->storageService->recalculateStorage($demo);

        Setting::put(self::KUNCI_WAKTU, now()->toIso8601String());
    }

    /** Perusahaan demo, dibuat bila belum ada. */
    private function perusahaan(): Company
    {
        $company = Company::firstOrNew(['name' => (string) config('demo.company')]);

        $company->fill([
            'default_quota' => (int) round(((float) config('demo.company_quota_gb')) * 1073741824),
            'max_users' => 10,
            'is_active' => true,
        ]);
        $company->save();

        return $company;
    }

    /** Akun demo dengan kredensial yang selalu sama. */
    private function bangunAkun(Company $company): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => (string) config('demo.name'),
            'username' => null,
            'email' => (string) config('demo.email'),
            'password' => Hash::make((string) config('demo.password')),
            'role' => User::ROLE_ADMIN,
            'storage_quota' => $company->default_quota,
            'storage_used' => 0,
            'is_active' => true,
            'api_token' => Str::random(64),
        ]);
    }

    /**
     * Isi contoh supaya demo tidak dibuka dalam keadaan kosong.
     *
     * Berkasnya benar-benar ditulis ke disk, bukan hanya barisnya di database -
     * kalau tidak, mengunduh atau melihat pratinjaunya akan gagal.
     */
    private function isiContoh(User $demo): void
    {
        $folders = [
            ['name' => 'Dokumen', 'path' => '/Dokumen'],
            ['name' => 'Gambar', 'path' => '/Gambar'],
            ['name' => 'Arsip 2026', 'path' => '/Arsip 2026'],
        ];

        foreach ($folders as $f) {
            FileFolder::create([
                'user_id' => $demo->id,
                'name' => $f['name'],
                'path' => $f['path'],
                'parent_path' => '/',
                'is_hidden' => false,
                'is_starred' => $f['name'] === 'Dokumen',
            ]);
        }

        $berkas = [
            [
                'nama' => 'Selamat Datang.txt',
                'folder' => '/',
                'bintang' => true,
                'isi' => "Selamat datang di demo Aldef Tech Drive.\n\n"
                    . "Silakan coba apa saja: unggah berkas, buat folder, seret untuk memindahkan,\n"
                    . "sembunyikan lewat klik kanan, atau bagikan lewat tautan.\n\n"
                    . "Seluruh isi akun ini dikembalikan seperti semula setiap 24 jam,\n"
                    . "jadi tidak perlu ragu mengubah apa pun.\n",
            ],
            [
                'nama' => 'Panduan Singkat.txt',
                'folder' => '/Dokumen',
                'bintang' => false,
                'isi' => "Panduan singkat\n\n"
                    . "1. Unggah  - tombol Unggah di kanan atas.\n"
                    . "2. Folder  - tombol Folder Baru, lalu seret berkas ke dalamnya.\n"
                    . "3. Bintang - ketuk ikon bintang untuk menandai, lihat di menu Berbintang.\n"
                    . "4. Kunci   - klik kanan berkas lalu Kunci untuk mengenkripsinya.\n"
                    . "5. Bagikan - klik kanan lalu Bagikan untuk membuat tautan.\n",
            ],
            [
                'nama' => 'Catatan Rapat.txt',
                'folder' => '/Arsip 2026',
                'bintang' => false,
                'isi' => "Catatan rapat contoh.\n\nBerkas ini hanya isian demo.\n",
            ],
        ];

        $direktori = storage_path('app/drive');

        if (!is_dir($direktori)) {
            mkdir($direktori, 0755, true);
        }

        foreach ($berkas as $b) {
            $namaDisk = 'demo_' . Str::random(24) . '.txt';
            $tujuan = $direktori . DIRECTORY_SEPARATOR . $namaDisk;

            file_put_contents($tujuan, $b['isi']);

            File::create([
                'user_id' => $demo->id,
                'name' => $namaDisk,
                'original_name' => $b['nama'],
                'mime_type' => 'text/plain',
                'size' => strlen($b['isi']),
                'path' => $namaDisk,
                'folder' => $b['folder'],
                'is_hidden' => false,
                'is_starred' => $b['bintang'],
            ]);
        }
    }

    /** Buang berkas milik seorang pengguna dari disk. */
    private function hapusBerkasFisik(User $user): void
    {
        foreach (File::where('user_id', $user->id)->get() as $file) {
            $path = storage_path('app/drive/' . $file->path);

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
