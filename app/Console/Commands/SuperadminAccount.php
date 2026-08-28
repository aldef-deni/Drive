<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pemulihan akun superadministrator dari server.
 *
 * Peran superadministrator tidak bisa diberikan lewat antarmuka mana pun -
 * itu memang disengaja. Konsekuensinya, sekali akun itu hilang atau turun
 * peran, tidak ada jalan masuk lagi lewat web. Perintah ini jalan keluarnya,
 * dan hanya bisa dijalankan oleh yang punya akses ke server.
 */
class SuperadminAccount extends Command
{
    protected $signature = 'drive:superadmin
                            {--username= : Username untuk masuk}
                            {--password= : Password baru. Dikosongkan berarti ditanyakan.}
                            {--name= : Nama tampilan, hanya dipakai saat membuat akun baru}
                            {--email= : Email, hanya dipakai saat membuat akun baru}';

    protected $description = 'Lihat, pulihkan, atau buat akun superadministrator';

    public function handle(): int
    {
        $username = trim((string) $this->option('username'));

        if ($username === '') {
            $this->daftarkan();

            return self::SUCCESS;
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
            $this->error('Username hanya boleh huruf, angka, titik, garis bawah, dan strip (3-60 karakter).');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: $this->secret('Password baru'));

        if (strlen($password) < 8) {
            $this->error('Password minimal 8 karakter.');

            return self::FAILURE;
        }

        $user = User::where('username', $username)->first();
        $baru = $user === null;

        if ($baru) {
            $email = trim((string) ($this->option('email') ?: $username . '@aldeftech.com'));

            if (User::where('email', $email)->exists()) {
                $this->error('Email ' . $email . ' sudah dipakai akun lain. Sebutkan --email yang lain.');

                return self::FAILURE;
            }

            $user = new User([
                'name' => trim((string) ($this->option('name') ?: 'Superadministrator')),
                'email' => $email,
                'storage_used' => 0,
            ]);
        }

        $user->username = $username;
        $user->password = Hash::make($password);
        $user->role = User::ROLE_SUPERADMIN;
        $user->is_active = true;

        // Superadministrator berdiri di atas seluruh perusahaan, jadi tidak
        // boleh terikat salah satunya - keterikatan itu justru mempersempit
        // apa yang terlihat olehnya.
        $user->company_id = null;

        if (!$user->storage_quota) {
            $user->storage_quota = 10 * 1073741824; // 10 GB
        }

        if (!$user->api_token) {
            $user->api_token = Str::random(64);
        }

        $user->save();

        $this->newLine();
        $this->info($baru ? 'Akun superadministrator dibuat.' : 'Akun ' . $username . ' dipulihkan sebagai superadministrator.');
        $this->line('Masuk memakai username: ' . $username);
        $this->newLine();

        $this->daftarkan();

        return self::SUCCESS;
    }

    private function daftarkan(): void
    {
        $daftar = User::where('role', User::ROLE_SUPERADMIN)->orderBy('id')->get();

        $this->line('<comment>Akun Superadministrator</comment>');
        $this->line(str_repeat('-', 60));

        if ($daftar->isEmpty()) {
            $this->error('Tidak ada satu pun akun superadministrator.');
            $this->line('Buat sekarang:');
            $this->line('  php artisan drive:superadmin --username=namaAnda');
            $this->newLine();

            return;
        }

        foreach ($daftar as $u) {
            $this->line(sprintf(
                'id=%-4s username=%-16s email=%-30s aktif=%s',
                $u->id,
                $u->username ?: '(kosong)',
                $u->email,
                $u->is_active ? 'ya' : 'TIDAK'
            ));

            if (!$u->username) {
                $this->warn('  Tanpa username, akun ini hanya bisa masuk memakai email.');
            }

            if (!$u->is_active) {
                $this->warn('  Akun nonaktif - tidak bisa masuk sampai diaktifkan.');
            }
        }

        $this->newLine();
        $this->line('Ganti username/password:');
        $this->line('  php artisan drive:superadmin --username=namaAnda');
        $this->newLine();
    }
}
