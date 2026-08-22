<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pemisahan data antar perusahaan dan peran superadministrator.
 *
 * Inti yang dijaga: admin perusahaan A tidak boleh melihat, mengubah, atau
 * menghapus apa pun milik perusahaan B — termasuk dengan menebak id di URL.
 */
class MultiCompanyTest extends TestCase
{
    use RefreshDatabase;

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
            'email' => 'u' . uniqid() . '@dekorasi.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'storage_quota' => User::DEFAULT_STORAGE_QUOTA,
            'storage_used' => 0,
            'is_active' => true,
        ], $extra));
    }

    // ---------------------------------------------------------------- Peran

    public function test_superadmin_berada_di_atas_admin(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $admin = $this->user($this->company('PT A'), User::ROLE_ADMIN);
        $biasa = $this->user($this->company('PT B'));

        $this->assertTrue($super->isSuperAdmin());
        $this->assertTrue($super->isAdmin(), 'Superadmin harus lolos pemeriksaan area admin');
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertFalse($biasa->isAdmin());

        // Superadmin boleh mengelola siapa pun; admin tidak boleh menyentuh superadmin.
        $this->assertTrue($super->canManage($admin));
        $this->assertFalse($admin->canManage($super));
    }

    public function test_akun_superadmin_bawaan_dibuat_oleh_migrasi(): void
    {
        // Akun ini disiapkan migrasi, bukan dibuat manual di test.
        $super = User::where('username', 'deniafrizal')->first();

        $this->assertNotNull($super, 'Migrasi harus menyiapkan akun superadministrator');
        $this->assertSame(User::ROLE_SUPERADMIN, $super->role);
        $this->assertNull($super->company_id, 'Superadmin tidak terikat perusahaan mana pun');
        $this->assertTrue($super->is_active);

        // Login memakai username, bukan email.
        $this->post('/login', ['email' => 'deniafrizal', 'password' => 'p0o9i8u7'])
            ->assertRedirect('/drive');

        $this->assertTrue(auth()->user()->isSuperAdmin());
    }

    public function test_akun_lama_dipindahkan_ke_perusahaan_bawaan(): void
    {
        // Migrasi memindahkan akun tanpa perusahaan ke satu perusahaan bawaan,
        // supaya tidak ada akun menggantung yang tak terlihat admin mana pun.
        $bawaan = Company::where('slug', 'dekorasi-me')->first();

        $this->assertNotNull($bawaan, 'Perusahaan bawaan harus dibuat migrasi');
        $this->assertSame(0, User::whereNull('company_id')
            ->where('role', '!=', User::ROLE_SUPERADMIN)
            ->count(), 'Tidak boleh ada akun non-superadmin tanpa perusahaan');
    }

    // ------------------------------------------------------- Pemisahan data

    public function test_admin_hanya_melihat_pengguna_perusahaannya(): void
    {
        $a = $this->company('PT A');
        $b = $this->company('PT B');

        $adminA = $this->user($a, User::ROLE_ADMIN, ['name' => 'Admin Alpha']);
        $this->user($a, User::ROLE_USER, ['name' => 'Anggota Alpha']);
        $this->user($b, User::ROLE_USER, ['name' => 'Anggota Beta']);

        $this->actingAs($adminA)->get('/admin/users')
            ->assertOk()
            ->assertSee('Anggota Alpha')
            ->assertDontSee('Anggota Beta');
    }

    public function test_statistik_dashboard_admin_hanya_menghitung_perusahaannya(): void
    {
        $a = $this->company('PT A');
        $b = $this->company('PT B');

        $adminA = $this->user($a, User::ROLE_ADMIN);
        $this->user($a);
        $this->user($b);
        $this->user($b);

        $stats = $this->actingAs($adminA)->get('/admin')->assertOk()->viewData('stats');

        // Admin A + satu anggota = 2, bukan 5.
        $this->assertSame(2, $stats['total_users']);
    }

    public function test_superadmin_melihat_seluruh_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $this->user($this->company('PT A'), User::ROLE_USER, ['name' => 'Anggota Alpha']);
        $this->user($this->company('PT B'), User::ROLE_USER, ['name' => 'Anggota Beta']);

        $this->actingAs($super)->get('/admin/users')
            ->assertOk()
            ->assertSee('Anggota Alpha')
            ->assertSee('Anggota Beta');
    }

    public function test_admin_tidak_bisa_menyentuh_pengguna_perusahaan_lain(): void
    {
        $adminA = $this->user($this->company('PT A'), User::ROLE_ADMIN);
        $korban = $this->user($this->company('PT B'), User::ROLE_USER, ['name' => 'Korban']);

        // Menebak id di URL tidak boleh berhasil.
        $this->actingAs($adminA)->get('/admin/users/' . $korban->id . '/edit')->assertForbidden();
        $this->actingAs($adminA)->post('/admin/users/' . $korban->id . '/toggle-status')->assertForbidden();
        $this->actingAs($adminA)->delete('/admin/users/' . $korban->id)->assertForbidden();
        $this->actingAs($adminA)->put('/admin/users/' . $korban->id, [
            'name' => 'Diretas',
            'email' => $korban->email,
            'role' => 'user',
            'storage_quota' => 104857600,
            'is_active' => '0',
        ])->assertForbidden();

        $this->assertSame('Korban', $korban->fresh()->name);
        $this->assertTrue($korban->fresh()->is_active);
    }

    public function test_file_pengguna_tidak_terhitung_lintas_perusahaan(): void
    {
        $a = $this->company('PT A');
        $adminA = $this->user($a, User::ROLE_ADMIN);
        $anggotaB = $this->user($this->company('PT B'));

        File::create([
            'user_id' => $anggotaB->id,
            'name' => 'rahasia.txt',
            'original_name' => 'rahasia-b.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'path' => $anggotaB->id . '/rahasia.txt',
            'folder' => '/',
        ]);

        $stats = $this->actingAs($adminA)->get('/admin')->assertOk()->viewData('stats');

        $this->assertSame(0, $stats['total_files'], 'File perusahaan lain tidak boleh ikut terhitung');
    }

    // ----------------------------------------------------- CRUD perusahaan

    public function test_hanya_superadmin_yang_boleh_mengelola_perusahaan(): void
    {
        $admin = $this->user($this->company('PT A'), User::ROLE_ADMIN);
        $biasa = $this->user($this->company('PT A'));

        foreach ([$admin, $biasa] as $pelaku) {
            $this->actingAs($pelaku)->get('/admin/companies')->assertForbidden();
            $this->actingAs($pelaku)->post('/admin/companies', ['name' => 'PT Nakal'])->assertForbidden();
        }

        $this->assertDatabaseMissing('companies', ['name' => 'PT Nakal']);
    }

    public function test_superadmin_bisa_crud_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        // Tambah
        $this->actingAs($super)->post('/admin/companies', [
            'name' => 'PT Cahaya Nusantara',
            'email' => 'kontak@cahaya.test',
            'default_quota_gb' => 2,
            'is_active' => '1',
        ])->assertRedirect(route('admin.companies.index'));

        $company = Company::where('name', 'PT Cahaya Nusantara')->firstOrFail();
        $this->assertSame('pt-cahaya-nusantara', $company->slug);
        $this->assertSame(2 * 1073741824, $company->default_quota);

        // Ubah
        $this->actingAs($super)->put('/admin/companies/' . $company->id, [
            'name' => 'PT Cahaya Baru',
            'default_quota_gb' => 5,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('PT Cahaya Baru', $company->fresh()->name);
        $this->assertSame(5 * 1073741824, $company->fresh()->default_quota);

        // Hapus
        $this->actingAs($super)->delete('/admin/companies/' . $company->id)->assertRedirect();
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function test_perusahaan_berpenghuni_tidak_bisa_dihapus(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Berpenghuni');
        $this->user($company);

        $this->actingAs($super)->delete('/admin/companies/' . $company->id)
            ->assertRedirect()
            ->assertSessionHas('error');

        // Menghapusnya akan membuat file penghuninya menggantung tanpa pemilik.
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_menonaktifkan_perusahaan_menutup_akses_penggunanya(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Tutup');
        $anggota = $this->user($company);

        $this->actingAs($super)->post('/admin/companies/' . $company->id . '/toggle')->assertRedirect();

        $this->assertFalse($company->fresh()->is_active);
        $this->assertFalse($anggota->fresh()->is_active, 'Pengguna ikut nonaktif');

        // Keluar dulu: route /login memakai middleware guest, jadi permintaan
        // dari sesi yang masih login akan dialihkan tanpa pesan kesalahan.
        auth()->logout();

        $this->post('/login', ['email' => $anggota->email, 'password' => 'password123'])
            ->assertSessionHasErrors('email');
    }

    public function test_superadmin_bisa_membuat_admin_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Baru');

        $this->actingAs($super)->post('/admin/companies/' . $company->id . '/admin', [
            'name' => 'Admin Baru',
            'email' => 'adminbaru@dekorasi.test',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect();

        $admin = User::where('email', 'adminbaru@dekorasi.test')->firstOrFail();

        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertSame($company->id, $admin->company_id);
        $this->assertTrue($admin->is_active, 'Admin buatan superadmin langsung aktif');
    }

    // ------------------------------------------------ Logo perusahaan

    /** Bersihkan logo yang tertulis ke disk selama pengujian. */
    private function bersihkanLogo(): void
    {
        $dir = storage_path(Company::LOGO_DIR);

        foreach (glob($dir . '/*') ?: [] as $berkas) {
            @unlink($berkas);
        }
    }

    public function test_logo_perusahaan_diunggah_dan_disajikan_lewat_route(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->actingAs($super)->post('/admin/companies', [
            'name' => 'PT Berlogo',
            'default_quota_gb' => 1,
            'is_active' => 1,
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 120, 120),
        ])->assertRedirect(route('admin.companies.index'));

        $company = Company::where('name', 'PT Berlogo')->firstOrFail();

        $this->assertNotNull($company->logo);
        $this->assertNotNull($company->logoPath(), 'Berkasnya harus benar-benar ada di disk');

        // Disajikan lewat route, bukan public/storage - symlink tidak bisa
        // diandalkan di cPanel.
        $this->assertStringContainsString('/company-logo/' . $company->id, $company->logoUrl());

        $this->get($company->logoUrl())->assertOk();

        $this->bersihkanLogo();
    }

    public function test_logo_perusahaan_menggantikan_logo_sidebar_penggunanya(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->actingAs($super)->post('/admin/companies', [
            'name' => 'PT Sidebar',
            'default_quota_gb' => 1,
            'is_active' => 1,
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 120, 120),
        ]);

        $berlogo = Company::where('name', 'PT Sidebar')->firstOrFail();
        $polos = $this->company('PT Polos');

        // Pengguna biasa di perusahaan berlogo melihat logo itu, bukan bawaan.
        $anggota = $this->user($berlogo);
        $this->actingAs($anggota)->get('/drive')
            ->assertOk()
            ->assertSee('/company-logo/' . $berlogo->id)
            ->assertSee('PT Sidebar');

        // Admin perusahaan yang sama juga.
        $admin = $this->user($berlogo, User::ROLE_ADMIN);
        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('/company-logo/' . $berlogo->id);

        // Perusahaan lain tidak ikut terbawa.
        $lain = $this->user($polos);
        $this->actingAs($lain)->get('/drive')
            ->assertOk()
            ->assertDontSee('/company-logo/' . $berlogo->id)
            ->assertSee('logo-dekorasi.png');

        $this->bersihkanLogo();
    }

    public function test_logo_perusahaan_bisa_diganti_dan_dihapus(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Ganti');

        $unggah = fn () => $this->actingAs($super)->put('/admin/companies/' . $company->id, [
            'name' => 'PT Ganti',
            'default_quota_gb' => 1,
            'is_active' => 1,
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 100, 100),
        ]);

        $unggah();
        $lama = $company->fresh()->logoPath();
        $this->assertNotNull($lama);

        // Mengganti logo tidak boleh meninggalkan berkas lama menumpuk.
        sleep(1); // nama berkas memakai detik; pastikan berbeda
        $unggah();
        $baru = $company->fresh()->logoPath();

        $this->assertNotSame($lama, $baru);
        $this->assertFileDoesNotExist($lama, 'Logo lama harus ikut dibuang');

        $this->actingAs($super)->put('/admin/companies/' . $company->id, [
            'name' => 'PT Ganti',
            'default_quota_gb' => 1,
            'is_active' => 1,
            'hapus_logo' => 1,
        ])->assertRedirect();

        $this->assertNull($company->fresh()->logo);
        $this->assertFileDoesNotExist($baru);

        $this->bersihkanLogo();
    }

    public function test_logo_menolak_berkas_yang_bukan_gambar(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->actingAs($super)->post('/admin/companies', [
            'name' => 'PT Nakal',
            'default_quota_gb' => 1,
            'is_active' => 1,
            'logo' => \Illuminate\Http\UploadedFile::fake()->create('skrip.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('logo');

        $this->assertDatabaseMissing('companies', ['name' => 'PT Nakal']);
    }

    // ------------------------------------- Pembuatan akun oleh superadmin

    public function test_superadmin_membuat_akun_yang_langsung_aktif(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Terima', ['default_quota' => 3 * 1073741824]);

        $this->actingAs($super)->get('/admin/users/create')
            ->assertOk()
            ->assertSee('PT Terima');

        $this->actingAs($super)->post('/admin/users', [
            'name' => 'Karyawan Baru',
            'email' => 'karyawan@dekorasi.test',
            'company_id' => $company->id,
            'role' => User::ROLE_USER,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect(route('admin.users'))->assertSessionHas('success');

        $baru = User::where('email', 'karyawan@dekorasi.test')->firstOrFail();

        $this->assertTrue($baru->is_active, 'Akun buatan superadmin tidak perlu diverifikasi lagi');
        $this->assertSame(User::ROLE_USER, $baru->role);
        $this->assertSame($company->id, $baru->company_id);
        $this->assertSame(3 * 1073741824, $baru->storage_quota, 'Kuota mengikuti perusahaan');

        // Langsung bisa dipakai, tanpa langkah tambahan apa pun.
        $this->post('/logout');
        $this->assertTrue(auth()->attempt([
            'email' => 'karyawan@dekorasi.test',
            'password' => 'rahasia12345',
        ]));
    }

    public function test_superadmin_bisa_membuat_akun_berperan_admin(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Kelola');

        $this->actingAs($super)->post('/admin/users', [
            'name' => 'Admin Baru',
            'email' => 'adminbaru2@dekorasi.test',
            'company_id' => $company->id,
            'role' => User::ROLE_ADMIN,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect(route('admin.users'));

        $admin = User::where('email', 'adminbaru2@dekorasi.test')->firstOrFail();

        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
    }

    public function test_peran_superadmin_tidak_bisa_dibuat_lewat_formulir(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Coba');

        // Peran tertinggi hanya boleh lahir dari migrasi; kalau bisa dibuat
        // lewat formulir, isolasi antar perusahaan kehilangan artinya.
        $this->actingAs($super)->post('/admin/users', [
            'name' => 'Calon Super',
            'email' => 'calonsuper@dekorasi.test',
            'company_id' => $company->id,
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'calonsuper@dekorasi.test']);
    }

    public function test_tambah_user_hanya_untuk_superadmin(): void
    {
        $company = $this->company('PT Batas');
        $admin = $this->user($company, User::ROLE_ADMIN);
        $biasa = $this->user($company);

        foreach ([$admin, $biasa] as $pelaku) {
            $this->actingAs($pelaku)->get('/admin/users/create')->assertForbidden();

            $this->actingAs($pelaku)->post('/admin/users', [
                'name' => 'Selundupan',
                'email' => 'selundupan@dekorasi.test',
                'company_id' => $company->id,
                'role' => User::ROLE_ADMIN,
                'password' => 'rahasia12345',
                'password_confirmation' => 'rahasia12345',
            ])->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'selundupan@dekorasi.test']);

        // Tombolnya pun tidak boleh terlihat oleh admin perusahaan.
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertDontSee('Tambah User');
    }

    public function test_akun_baru_ditolak_bila_perusahaan_penuh_atau_nonaktif(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $penuh = $this->company('PT Penuh', ['max_users' => 1]);
        $this->user($penuh);

        $this->actingAs($super)->post('/admin/users', [
            'name' => 'Kelebihan',
            'email' => 'kelebihan@dekorasi.test',
            'company_id' => $penuh->id,
            'role' => User::ROLE_USER,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('company_id');

        $mati = $this->company('PT Mati', ['is_active' => false]);

        $this->actingAs($super)->post('/admin/users', [
            'name' => 'Tetap Ditolak',
            'email' => 'ditolak@dekorasi.test',
            'company_id' => $mati->id,
            'role' => User::ROLE_USER,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('company_id');

        $this->assertDatabaseMissing('users', ['email' => 'kelebihan@dekorasi.test']);
        $this->assertDatabaseMissing('users', ['email' => 'ditolak@dekorasi.test']);
    }

    // ------------------------------------------------------------ Registrasi

    public function test_daftar_perusahaan_di_form_daftar_hanya_berisi_perusahaan(): void
    {
        $this->company('PT Satu');
        $this->company('PT Dua');

        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('PT Satu', $html);
        $this->assertStringContainsString('PT Dua', $html);

        // Teks bantunya harus tetap terbaca di kolom, tetapi tidak boleh ikut
        // berbaris sebagai pilihan - pemakai pernah mengira itu perusahaan.
        $this->assertMatchesRegularExpression(
            '/<option value=""[^>]*\bdisabled\b[^>]*\bhidden\b[^>]*>\s*Pilih perusahaan Anda/',
            $html
        );
    }

    public function test_registrasi_mengikat_akun_ke_perusahaan_terpilih(): void
    {
        $company = $this->company('PT Daftar', ['default_quota' => 3 * 1073741824]);

        $this->post('/register', [
            'name' => 'Calon',
            'email' => 'calon@dekorasi.test',
            'company_id' => $company->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect(route('register'));

        $baru = User::where('email', 'calon@dekorasi.test')->firstOrFail();

        $this->assertSame($company->id, $baru->company_id);
        $this->assertFalse($baru->is_active);
        // Kuota mengikuti pengaturan perusahaan, bukan nilai global.
        $this->assertSame(3 * 1073741824, $baru->storage_quota);
    }

    public function test_registrasi_ditolak_bila_perusahaan_penuh(): void
    {
        $company = $this->company('PT Penuh', ['max_users' => 1]);
        $this->user($company);

        $this->post('/register', [
            'name' => 'Terlambat',
            'email' => 'terlambat@dekorasi.test',
            'company_id' => $company->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('company_id');

        $this->assertDatabaseMissing('users', ['email' => 'terlambat@dekorasi.test']);
    }

    public function test_notifikasi_pendaftaran_hanya_ke_admin_perusahaan_itu(): void
    {
        $a = $this->company('PT A');
        $b = $this->company('PT B');

        $adminA = $this->user($a, User::ROLE_ADMIN);
        $adminB = $this->user($b, User::ROLE_ADMIN);
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->post('/register', [
            'name' => 'Calon A',
            'email' => 'calona@dekorasi.test',
            'company_id' => $a->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ]);

        $this->assertDatabaseHas('notifications', ['user_id' => $adminA->id, 'type' => 'new_registration']);
        $this->assertDatabaseHas('notifications', ['user_id' => $super->id, 'type' => 'new_registration']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $adminB->id, 'type' => 'new_registration']);
    }
}
