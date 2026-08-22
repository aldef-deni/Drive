<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Pemeriksa kesehatan deployment.
 *
 * Dibuat setelah dua kegagalan produksi yang penyebabnya sama: file penting
 * tidak pernah sampai ke server karena deploy memakai arsip berisi file yang
 * berubah saja. Gejalanya menyesatkan — `migrate` berkata "Nothing to migrate",
 * dan aplikasi mobile hanya menampilkan "Gagal memuat".
 *
 * Jalankan setelah setiap deploy: php artisan drive:check
 */
class CheckDeployment extends Command
{
    protected $signature = 'drive:check';

    protected $description = 'Periksa apakah deployment lengkap dan siap dipakai';

    private int $gagal = 0;
    private int $peringatan = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Pemeriksaan Deployment Dekorasi Drive</>');
        $this->newLine();

        $this->periksaGuardApi();
        $this->periksaSkema();
        $this->periksaMultiPerusahaan();
        $this->periksaPenyimpanan();
        $this->periksaPhp();
        $this->periksaKeamanan();

        $this->newLine();

        if ($this->gagal > 0) {
            $this->error("  {$this->gagal} masalah harus diperbaiki sebelum aplikasi bisa dipakai.");
            $this->line('  Setelah memperbaiki, jalankan: php artisan config:clear && php artisan config:cache');
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('  Semua pemeriksaan penting lolos.'
            . ($this->peringatan > 0 ? " ({$this->peringatan} peringatan)" : ''));
        $this->newLine();

        return self::SUCCESS;
    }

    private function periksaGuardApi(): void
    {
        $guard = config('auth.guards.api');

        if (!$guard) {
            $this->gagal(
                'Guard "api" tidak ada di config/auth.php',
                'Seluruh route /api/* akan membalas error 500 dan aplikasi mobile tidak bisa dipakai. '
                . 'Unggah ulang config/auth.php, lalu bangun ulang config cache.'
            );

            return;
        }

        if (($guard['driver'] ?? null) !== 'token') {
            $this->gagal('Guard "api" ada tetapi drivernya bukan "token"', 'Periksa config/auth.php.');

            return;
        }

        $this->lolos('Guard "api" terpasang (driver token)');
    }

    private function periksaSkema(): void
    {
        try {
            if (Schema::hasColumn('users', 'api_token')) {
                $this->lolos('Kolom users.api_token ada');
            } else {
                $this->gagal(
                    'Kolom users.api_token belum ada',
                    'Registrasi dan login lewat aplikasi akan gagal. Jalankan: php artisan migrate --force'
                );
            }

            if (Schema::hasTable('settings')) {
                $this->lolos('Tabel settings ada');
                $this->periksaKataKunci();
            } else {
                $this->gagal(
                    'Tabel settings belum ada',
                    'Kata kunci Hidden System tidak bisa disimpan. Jalankan: php artisan migrate --force'
                );
            }
        } catch (\Throwable $e) {
            $this->gagal('Tidak bisa memeriksa database', $e->getMessage());
        }
    }

    /**
     * Bentuk penyimpanan kata kunci Hidden System.
     *
     * Diperiksa karena kegagalannya tidak terlihat dari mana pun: halamannya
     * tetap terbuka, kata kuncinya tetap berfungsi, hanya nilainya yang tidak
     * bisa ditampilkan - dan itu justru saat paling dibutuhkan (lupa).
     */
    private function periksaKataKunci(): void
    {
        $bentuk = \App\Models\Setting::hiddenKeywordState();

        if ($bentuk === \App\Models\Setting::STATE_READABLE) {
            $this->lolos('Kata kunci Hidden System tersimpan terenkripsi dan bisa ditampilkan');

            return;
        }

        if ($bentuk === \App\Models\Setting::STATE_DEFAULT) {
            $this->peringatan(
                'Kata kunci Hidden System masih bawaan',
                'Ganti lewat menu Hidden System, atau: php artisan drive:hidden-keyword "kunciAnda"'
            );

            return;
        }

        if ($bentuk === \App\Models\Setting::STATE_LEGACY) {
            $this->peringatan(
                'Kata kunci Hidden System masih format lama (tidak bisa ditampilkan)',
                'Masih berfungsi, tetapi nilainya tidak bisa dilihat. Tetapkan sekali: php artisan drive:hidden-keyword "kunciAnda"'
            );

            return;
        }

        $this->gagal(
            'Kata kunci Hidden System tidak bisa dibuka',
            'APP_KEY berubah setelah kata kunci disimpan, sehingga kata kunci lama juga sudah tidak berfungsi. '
            . 'Tetapkan yang baru: php artisan drive:hidden-keyword "kunciAnda"'
        );
    }

    private function periksaMultiPerusahaan(): void
    {
        try {
            if (!Schema::hasTable('companies')) {
                $this->gagal(
                    'Tabel companies belum ada',
                    'Menu Perusahaan dan pemisahan data tidak akan bekerja. Jalankan: php artisan migrate --force'
                );

                return;
            }

            if (!Schema::hasColumn('users', 'company_id')) {
                $this->gagal(
                    'Kolom users.company_id belum ada',
                    'Data antar perusahaan berisiko tercampur. Jalankan: php artisan migrate --force'
                );

                return;
            }

            $this->lolos('Skema multi-perusahaan lengkap (' . \App\Models\Company::count() . ' perusahaan)');

            // Akun tanpa perusahaan tidak terlihat admin mana pun.
            $menggantung = \App\Models\User::whereNull('company_id')
                ->where('role', '!=', \App\Models\User::ROLE_SUPERADMIN)
                ->count();

            if ($menggantung > 0) {
                $this->peringatan(
                    "{$menggantung} akun tidak terhubung ke perusahaan mana pun",
                    'Akun ini tidak akan terlihat oleh admin mana pun. Tetapkan perusahaannya lewat Manajemen User.'
                );
            } else {
                $this->lolos('Semua akun terhubung ke perusahaan');
            }

            $super = \App\Models\User::where('role', \App\Models\User::ROLE_SUPERADMIN)->count();

            if ($super === 0) {
                $this->gagal(
                    'Tidak ada akun superadministrator',
                    'Tidak ada yang bisa mengelola perusahaan. Jalankan: php artisan migrate --force'
                );
            } else {
                $this->lolos("Superadministrator tersedia ({$super} akun)");
            }
        } catch (\Throwable $e) {
            $this->gagal('Tidak bisa memeriksa skema perusahaan', $e->getMessage());
        }
    }

    private function periksaPenyimpanan(): void
    {
        foreach ([
            'storage/app/drive' => 'Tempat file pengguna disimpan',
            'storage/app/public/avatars' => 'Tempat foto profil disimpan',
            'storage/logs' => 'Tempat catatan error disimpan',
        ] as $relatif => $keterangan) {
            $path = base_path($relatif);

            if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
                $this->gagal("Folder {$relatif} tidak ada dan tidak bisa dibuat", $keterangan);
                continue;
            }

            if (!is_writable($path)) {
                $this->gagal("Folder {$relatif} tidak bisa ditulis", 'Perbaiki izin folder menjadi 755.');
                continue;
            }

            $this->lolos("Folder {$relatif} bisa ditulis");
        }
    }

    private function periksaPhp(): void
    {
        $upload = ini_get('upload_max_filesize');
        $post = ini_get('post_max_size');
        $tmp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();

        $this->lolos("Batas unggah PHP: upload_max_filesize={$upload}, post_max_size={$post}");

        if (!is_dir($tmp) || !is_writable($tmp)) {
            $this->gagal(
                "Folder sementara PHP tidak bisa dipakai: {$tmp}",
                'Semua unggahan akan gagal. Periksa pengaturan upload_tmp_dir pada hosting.'
            );

            return;
        }

        $this->lolos("Folder sementara PHP bisa ditulis: {$tmp}");
    }

    private function periksaKeamanan(): void
    {
        if (config('app.debug')) {
            $this->peringatan(
                'APP_DEBUG masih menyala',
                'Setiap error menampilkan potongan kode dan isi database ke pengunjung. '
                . 'Ubah .env menjadi APP_DEBUG=false dan APP_ENV=production.'
            );

            return;
        }

        $this->lolos('APP_DEBUG mati');
    }

    private function lolos(string $pesan): void
    {
        $this->line("  <fg=green>OK</>    {$pesan}");
    }

    private function gagal(string $pesan, string $saran): void
    {
        $this->gagal++;
        $this->line("  <fg=red;options=bold>GAGAL</> {$pesan}");
        $this->line("        <fg=gray>{$saran}</>");
    }

    private function peringatan(string $pesan, string $saran): void
    {
        $this->peringatan++;
        $this->line("  <fg=yellow;options=bold>PERHATIAN</> {$pesan}");
        $this->line("            <fg=gray>{$saran}</>");
    }
}
