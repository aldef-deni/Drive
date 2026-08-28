<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengelolaan kuota penyimpanan oleh superadministrator.
 */
class QuotaManagementTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1073741824;

    private function company(string $nama, array $extra = []): Company
    {
        return Company::create(array_merge([
            'name' => $nama,
            'default_quota' => User::DEFAULT_STORAGE_QUOTA,
            'is_active' => true,
        ], $extra));
    }

    private function user(?Company $company, string $role = User::ROLE_USER, array $extra = []): User
    {
        return User::create(array_merge([
            'company_id' => $company?->id,
            'name' => 'Pengguna ' . uniqid(),
            'email' => 'u' . uniqid() . '@aldeftech.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'storage_quota' => User::DEFAULT_STORAGE_QUOTA,
            'storage_used' => 0,
            'is_active' => true,
        ], $extra));
    }

    // ---------------------------------------------------- Akun demo

    private function demo(): \App\Services\DemoResetService
    {
        return app(\App\Services\DemoResetService::class);
    }

    public function test_pemulihan_demo_membangun_akun_dan_isi_contohnya(): void
    {
        $this->demo()->pulihkan();

        $akun = User::where('email', config('demo.email'))->firstOrFail();

        $this->assertSame(User::ROLE_ADMIN, $akun->role);
        $this->assertTrue($akun->is_active);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(config('demo.password'), $akun->password));

        // Perusahaan tersendiri: akun demo berperan admin, dan admin melihat
        // seluruh pengguna di perusahaannya. Menempatkannya di perusahaan asli
        // berarti membuka data pelanggan kepada siapa pun yang mencoba demo.
        $this->assertSame(config('demo.company'), $akun->company->name);

        $this->assertGreaterThan(0, \App\Models\File::where('user_id', $akun->id)->count(),
            'Demo tidak boleh dibuka dalam keadaan kosong');
        $this->assertGreaterThan(0, \App\Models\FileFolder::where('user_id', $akun->id)->count());
    }

    public function test_pemulihan_demo_menghapus_jejak_pengunjung_sebelumnya(): void
    {
        $this->demo()->pulihkan();
        $akun = User::where('email', config('demo.email'))->firstOrFail();
        $company = $akun->company;

        // Pengunjung mengubah banyak hal: ganti password, unggah berkas,
        // membuat akun baru, dan menonaktifkan dirinya sendiri.
        $akun->update([
            'password' => \Illuminate\Support\Facades\Hash::make('sudahDiubah99'),
            'name' => 'Diubah Pengunjung',
            'is_active' => false,
        ]);

        \App\Models\File::create([
            'user_id' => $akun->id,
            'name' => 'sampah.txt',
            'original_name' => 'sampah.txt',
            'path' => 'sampah.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
            'folder' => '/',
            'is_hidden' => false,
        ]);

        $titipan = $this->user($company, User::ROLE_USER, ['name' => 'Akun Titipan']);

        $this->demo()->pulihkan();

        $pulih = User::where('email', config('demo.email'))->firstOrFail();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(config('demo.password'), $pulih->password),
            'Password yang diganti pengunjung harus kembali');
        $this->assertTrue($pulih->is_active);
        $this->assertSame(config('demo.name'), $pulih->name);

        $this->assertDatabaseMissing('users', ['id' => $titipan->id]);
        $this->assertSame(0, \App\Models\File::where('original_name', 'sampah.txt')->count());
    }

    public function test_pemulihan_demo_tidak_menyentuh_perusahaan_lain(): void
    {
        $lain = $this->company('PT Bukan Demo');
        $pelanggan = $this->user($lain, User::ROLE_USER, ['name' => 'Pelanggan Asli']);

        $this->demo()->pulihkan();

        $this->assertDatabaseHas('users', ['id' => $pelanggan->id, 'name' => 'Pelanggan Asli']);
        $this->assertDatabaseHas('companies', ['id' => $lain->id]);
    }

    public function test_pemulihan_demo_hanya_sekali_dalam_selang_waktunya(): void
    {
        $this->assertTrue($this->demo()->pulihkanBilaPerlu(), 'Pertama kali selalu dipulihkan');

        // Sudah dipulihkan barusan; percobaan berikutnya tidak boleh membangun
        // ulang - kalau tidak, tiap percobaan masuk menghapus pekerjaan orang
        // yang sedang mencoba.
        $this->assertFalse($this->demo()->pulihkanBilaPerlu());
    }

    public function test_login_demo_memicu_pemulihan_walau_password_sudah_diganti(): void
    {
        $this->demo()->pulihkan();
        $akun = User::where('email', config('demo.email'))->firstOrFail();

        // Pengunjung mengganti password, lalu waktunya lewat.
        $akun->update(['password' => \Illuminate\Support\Facades\Hash::make('dikunciPengunjung')]);
        \App\Models\Setting::put('demo_last_reset', now()->subDays(2)->toIso8601String());

        // Masuk dengan password aslinya: pemulihan berjalan lebih dulu, jadi
        // password itu berlaku kembali dan tidak ada yang terkunci.
        $this->post('/login', [
            'email' => config('demo.email'),
            'password' => config('demo.password'),
        ]);

        $this->assertAuthenticated();
    }

    public function test_akun_demo_tidak_bisa_mengganti_kata_kunci_rahasia(): void
    {
        $this->demo()->pulihkan();
        $akun = User::where('email', config('demo.email'))->firstOrFail();

        \App\Models\Setting::setHiddenKeyword('kunciAsliPelanggan');

        // Kata kunci ini berlaku lintas perusahaan. Membiarkan akun demo
        // menggantinya berarti seluruh pelanggan kehilangan akses ke file
        // tersembunyi mereka.
        $this->actingAs($akun)->put('/admin/hidden-system', [
            'current_password' => config('demo.password'),
            'keyword' => 'dibajakDemo',
            'keyword_confirmation' => 'dibajakDemo',
        ])->assertRedirect();

        $this->assertTrue(\App\Models\Setting::matchesHiddenKeyword('kunciAsliPelanggan'),
            'Kata kunci pelanggan harus tetap berlaku');
        $this->assertFalse(\App\Models\Setting::matchesHiddenKeyword('dibajakDemo'));
    }

    public function test_layar_masuk_menyediakan_tombol_demo(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        // Tombolnya mengisi kolom, bukan langsung mengirim - jadi yang diuji
        // adalah pemicunya beserta kredensial yang akan diisikan.
        $this->assertStringContainsString('isiDemo()', $html);
        $this->assertStringContainsString(config('demo.email'), $html);
        $this->assertStringContainsString('Demo', $html);
    }

    // ------------------------------------------- Penghapusan notifikasi

    private function notif(User $user, string $judul = 'Uji'): \App\Models\Notification
    {
        return \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'uji',
            'title' => $judul,
            'message' => 'Isi notifikasi',
            'icon' => 'fas fa-bell',
            'color' => 'blue',
            'url' => null,
        ]);
    }

    public function test_hapus_semua_notifikasi_hanya_menyentuh_milik_sendiri(): void
    {
        $company = $this->company('PT Notif');
        $saya = $this->user($company);
        $orangLain = $this->user($company);

        $this->notif($saya);
        $this->notif($saya);
        $this->notif($orangLain, 'Punya Orang Lain');

        $this->actingAs($saya)->delete('/notifications')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, \App\Models\Notification::where('user_id', $saya->id)->count());
        $this->assertSame(1, \App\Models\Notification::where('user_id', $orangLain->id)->count(),
            'Membersihkan kotak sendiri tidak boleh menyentuh milik orang lain');
    }

    public function test_hapus_semua_notifikasi_lewat_api(): void
    {
        $company = $this->company('PT Notif App');
        $saya = $this->user($company);
        $orangLain = $this->user($company);

        $this->notif($saya);
        $this->notif($orangLain);

        $saya->update(['api_token' => \Illuminate\Support\Str::random(64)]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $saya->api_token,
            'Accept' => 'application/json',
        ])->delete('/api/notifications')->assertOk()->assertJson(['success' => true, 'deleted' => 1]);

        $this->assertSame(0, \App\Models\Notification::where('user_id', $saya->id)->count());
        $this->assertSame(1, \App\Models\Notification::where('user_id', $orangLain->id)->count());
    }

    public function test_hapus_notifikasi_saat_kosong_tidak_error(): void
    {
        $saya = $this->user($this->company('PT Kosong Notif'));

        $this->actingAs($saya)->delete('/notifications')
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_halaman_notifikasi_menyediakan_konfirmasi_sebelum_menghapus(): void
    {
        $saya = $this->user($this->company('PT Konfirmasi'));
        $this->notif($saya);

        // Penghapusan massal tidak bisa dibatalkan, jadi tombolnya wajib
        // melewati konfirmasi - bukan langsung mengirim formulir.
        $html = $this->actingAs($saya)->get('/notifications')->assertOk()->getContent();

        $this->assertStringContainsString('Apakah Anda yakin untuk menghapus semua notifikasi', $html);
        $this->assertStringContainsString('Iya, Hapus', $html);
        $this->assertStringContainsString('Batal', $html);
    }

    // ------------------------------------------- Peringatan kuota menipis

    private function periksa(User $user): void
    {
        \App\Models\Notification::createAndCheckQuota($user->fresh());
    }

    private function adaPeringatan(User $user): bool
    {
        return \App\Models\Notification::where('user_id', $user->id)
            ->where('type', 'quota_low')->exists();
    }

    public function test_peringatan_kuota_tidak_muncul_saat_masih_lega(): void
    {
        // Regresi: ambangnya pernah 1 GB, sama dengan kuota bawaan akun baru,
        // sehingga peringatan muncul sejak unggahan pertama - saat kuota masih
        // 96% kosong.
        $user = $this->user($this->company('PT Lega'), User::ROLE_USER, [
            'storage_quota' => self::GB,
            'storage_used' => 41 * 1048576, // 41 MB dari 1 GB
        ]);

        $this->periksa($user);

        $this->assertFalse($this->adaPeringatan($user),
            'Sisa 983 MB dari 1 GB bukan kondisi hampir habis');
    }

    public function test_peringatan_muncul_saat_sisa_di_bawah_50_mb(): void
    {
        $user = $this->user($this->company('PT Menipis'), User::ROLE_USER, [
            'storage_quota' => self::GB,
            'storage_used' => self::GB - (49 * 1048576), // sisa 49 MB
        ]);

        $this->periksa($user);

        $this->assertTrue($this->adaPeringatan($user));
    }

    public function test_peringatan_tepat_di_batas_50_mb_belum_muncul(): void
    {
        $user = $this->user($this->company('PT Batas'), User::ROLE_USER, [
            'storage_quota' => self::GB,
            'storage_used' => self::GB - \App\Models\Notification::QUOTA_WARNING_THRESHOLD,
        ]);

        $this->periksa($user);

        $this->assertFalse($this->adaPeringatan($user),
            'Sisa persis 50 MB belum di bawah ambang');
    }

    public function test_kuota_habis_diberi_pesan_yang_berbeda(): void
    {
        $user = $this->user($this->company('PT Penuh Sesak'), User::ROLE_USER, [
            'storage_quota' => self::GB,
            'storage_used' => self::GB,
        ]);

        $this->periksa($user);

        $notif = \App\Models\Notification::where('user_id', $user->id)
            ->where('type', 'quota_low')->firstOrFail();

        // "Sisa kuota tinggal 0 B" membingungkan; kondisi penuh perlu kalimat
        // sendiri berikut jalan keluarnya.
        $this->assertSame('Kuota Penyimpanan Habis', $notif->title);
        $this->assertStringContainsString('sudah penuh', $notif->message);
    }

    public function test_kuota_kecil_tidak_memicu_peringatan_terus_menerus(): void
    {
        // Kuota 10 MB selalu di bawah ambang 50 MB, bahkan saat drive kosong.
        $user = $this->user($this->company('PT Mungil'), User::ROLE_USER, [
            'storage_quota' => 10 * 1048576,
            'storage_used' => 0,
        ]);

        $this->periksa($user);
        $this->assertFalse($this->adaPeringatan($user));

        // Tetapi saat benar-benar menipis, peringatannya harus tetap datang.
        $user->update(['storage_used' => 10 * 1048576 - 100]);
        $this->periksa($user);

        $this->assertTrue($this->adaPeringatan($user));
    }

    public function test_peringatan_kuota_paling_banyak_sekali_sehari(): void
    {
        $user = $this->user($this->company('PT Sekali'), User::ROLE_USER, [
            'storage_quota' => self::GB,
            'storage_used' => self::GB - 1048576,
        ]);

        $this->periksa($user);
        $this->periksa($user);
        $this->periksa($user);

        $this->assertSame(1, \App\Models\Notification::where('user_id', $user->id)
            ->where('type', 'quota_low')->count(),
            'Mengulang tiap unggahan hanya menenggelamkan notifikasi lain');
    }

    // ------------------------------------------------------------ Akses

    public function test_hanya_superadmin_yang_bisa_membuka_kelola_kuota(): void
    {
        $company = $this->company('PT A');
        $admin = $this->user($company, User::ROLE_ADMIN);
        $biasa = $this->user($company);
        $korban = $this->user($company);

        foreach ([$admin, $biasa] as $pelaku) {
            $this->actingAs($pelaku)->get('/admin/quotas')->assertForbidden();

            $this->actingAs($pelaku)
                ->put('/admin/quotas/' . $korban->id, ['quota_gb' => 99])
                ->assertForbidden();

            $this->actingAs($pelaku)
                ->post('/admin/quotas/bulk', ['quota_gb' => 99, 'target' => 'all'])
                ->assertForbidden();
        }

        // Kuota tidak boleh berubah sedikit pun oleh percobaan di atas.
        $this->assertSame(User::DEFAULT_STORAGE_QUOTA, $korban->fresh()->storage_quota);
    }

    public function test_superadmin_melihat_pengguna_seluruh_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $this->user($this->company('PT A'), User::ROLE_USER, ['name' => 'Anggota Alpha']);
        $this->user($this->company('PT B'), User::ROLE_USER, ['name' => 'Anggota Beta']);

        $this->actingAs($super)->get('/admin/quotas')
            ->assertOk()
            ->assertSee('Anggota Alpha')
            ->assertSee('Anggota Beta');
    }

    // ------------------------------------------------- Ubah satu pengguna

    public function test_kuota_satu_pengguna_bisa_diubah(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 5])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(5 * self::GB, $target->fresh()->storage_quota);
    }

    public function test_perubahan_kuota_memberi_tahu_penggunanya(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        $this->actingAs($super)->put('/admin/quotas/' . $target->id, ['quota_gb' => 5]);

        // Perubahan kuota terasa langsung oleh pengguna; jangan sampai mereka
        // baru tahu dari kegagalan unggah.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $target->id,
            'type' => 'quota_changed',
        ]);
    }

    public function test_kuota_di_bawah_pemakaian_diperingatkan_tapi_tetap_disimpan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'), User::ROLE_USER, [
            'storage_used' => 3 * self::GB,
        ]);

        $response = $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 1])
            ->assertRedirect();

        $this->assertSame(1 * self::GB, $target->fresh()->storage_quota);
        $this->assertStringContainsString('di bawah pemakaian', session('success'));
    }

    public function test_kuota_angka_bulat_bisa_disimpan_persis(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        // Regresi: input memakai min="0.01" dengan step="0.1", sehingga browser
        // hanya menganggap 0.01, 0.11, ... 99.91, 100.01 sebagai nilai sah dan
        // menolak angka bulat seperti 100.
        foreach ([1, 10, 50, 100, 512] as $gb) {
            $this->actingAs($super)
                ->put('/admin/quotas/' . $target->id, ['quota_gb' => $gb])
                ->assertSessionHasNoErrors();

            $this->assertSame($gb * self::GB, $target->fresh()->storage_quota,
                "Kuota {$gb} GB harus tersimpan persis");
        }
    }

    public function test_input_kuota_tidak_memaksa_kelipatan_tertentu(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $this->user($this->company('PT A'));

        $html = $this->actingAs($super)->get('/admin/quotas')->assertOk()->getContent();

        // step="0.1" berpasangan dengan min="0.01" adalah sumber penolakan
        // angka bulat oleh browser. step="any" membebaskan nilainya.
        $this->assertStringNotContainsString('step="0.1"', $html);
        $this->assertStringContainsString('step="any"', $html);
    }

    public function test_kuota_pecahan_bebas_tetap_diterima(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 2.75])
            ->assertSessionHasNoErrors();

        $this->assertSame((int) round(2.75 * self::GB), $target->fresh()->storage_quota);
    }

    public function test_kuota_tidak_valid_ditolak(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 0])
            ->assertSessionHasErrors('quota_gb');

        $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 'banyak'])
            ->assertSessionHasErrors('quota_gb');

        $this->assertSame(User::DEFAULT_STORAGE_QUOTA, $target->fresh()->storage_quota);
    }

    // --------------------------------------------------------- Massal

    public function test_atur_massal_satu_perusahaan_tidak_menyentuh_perusahaan_lain(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $a = $this->company('PT A');
        $b = $this->company('PT B');

        $anggotaA1 = $this->user($a);
        $anggotaA2 = $this->user($a);
        $anggotaB = $this->user($b);

        $this->actingAs($super)->post('/admin/quotas/bulk', [
            'quota_gb' => 7,
            'target' => 'company',
            'company_id' => $a->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(7 * self::GB, $anggotaA1->fresh()->storage_quota);
        $this->assertSame(7 * self::GB, $anggotaA2->fresh()->storage_quota);
        $this->assertSame(User::DEFAULT_STORAGE_QUOTA, $anggotaB->fresh()->storage_quota,
            'Perusahaan lain tidak boleh ikut berubah');
    }

    public function test_atur_massal_seluruh_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $a = $this->user($this->company('PT A'));
        $b = $this->user($this->company('PT B'));

        $this->actingAs($super)->post('/admin/quotas/bulk', [
            'quota_gb' => 2,
            'target' => 'all',
        ])->assertRedirect();

        $this->assertSame(2 * self::GB, $a->fresh()->storage_quota);
        $this->assertSame(2 * self::GB, $b->fresh()->storage_quota);
    }

    public function test_atur_massal_wajib_menyebut_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->actingAs($super)->post('/admin/quotas/bulk', [
            'quota_gb' => 3,
            'target' => 'company',
        ])->assertSessionHasErrors('company_id');
    }

    // ------------------------------------------- Samakan ke kuota bawaan

    public function test_samakan_ke_kuota_bawaan_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Rapi', ['default_quota' => 4 * self::GB]);

        $menyimpang = $this->user($company, User::ROLE_USER, ['storage_quota' => 100 * 1048576]);
        $sudahPas = $this->user($company, User::ROLE_USER, ['storage_quota' => 4 * self::GB]);

        $this->actingAs($super)
            ->post('/admin/quotas/company/' . $company->id . '/default')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(4 * self::GB, $menyimpang->fresh()->storage_quota);
        $this->assertSame(4 * self::GB, $sudahPas->fresh()->storage_quota);

        // Yang sudah sesuai tidak perlu diberi notifikasi ulang.
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $sudahPas->id,
            'type' => 'quota_changed',
        ]);
    }

    public function test_perusahaan_tanpa_pengguna_memberi_pesan_jelas(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $kosong = $this->company('PT Kosong');

        $this->actingAs($super)
            ->post('/admin/quotas/company/' . $kosong->id . '/default')
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
