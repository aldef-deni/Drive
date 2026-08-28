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
            'email' => 'u' . uniqid() . '@aldeftech.test',
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
            'email' => 'adminbaru@aldeftech.test',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect();

        $admin = User::where('email', 'adminbaru@aldeftech.test')->firstOrFail();

        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertSame($company->id, $admin->company_id);
        $this->assertTrue($admin->is_active, 'Admin buatan superadmin langsung aktif');
    }

    // -------------------------------- Pemulihan akun superadministrator

    public function test_formulir_edit_menyediakan_peran_superadmin_bagi_superadmin(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT Sunting'), User::ROLE_ADMIN);

        // Regresi: pilihan Superadmin tidak ada, sehingga membuka akun
        // superadmin di formulir ini membuat browser memilih opsi pertama
        // (User) dan menyimpannya menurunkan perannya diam-diam.
        $html = $this->actingAs($super)->get('/admin/users/' . $target->id . '/edit')
            ->assertOk()->getContent();

        $this->assertStringContainsString('value="superadmin"', $html);

        $this->app['auth']->forgetGuards();

        $admin = $this->user($this->company('PT Lain Sunting'), User::ROLE_ADMIN);
        $bawahan = $this->user($admin->company, User::ROLE_USER);

        $html = $this->actingAs($admin)->get('/admin/users/' . $bawahan->id . '/edit')
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('value="superadmin"', $html,
            'Admin perusahaan tidak boleh bisa mengangkat siapa pun jadi superadmin');
    }

    public function test_superadmin_tidak_terhalang_menyunting_akunnya_sendiri(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN, ['name' => 'Nama Lama']);

        // Penjagaan lama membandingkan peran dengan 'admin', sehingga
        // superadministrator selalu tertolak saat menyunting dirinya sendiri.
        $this->actingAs($super)->put('/admin/users/' . $super->id, [
            'name' => 'Nama Baru',
            'email' => $super->email,
            'role' => User::ROLE_SUPERADMIN,
            'storage_quota' => 10 * 1073741824,
            'is_active' => 1,
        ])->assertRedirect(route('admin.users'));

        $this->assertSame('Nama Baru', $super->fresh()->name);
        $this->assertSame(User::ROLE_SUPERADMIN, $super->fresh()->role);
    }

    public function test_superadmin_tidak_bisa_menurunkan_peran_sendiri(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->actingAs($super)->put('/admin/users/' . $super->id, [
            'name' => $super->name,
            'email' => $super->email,
            'role' => User::ROLE_ADMIN,
            'storage_quota' => 10 * 1073741824,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame(User::ROLE_SUPERADMIN, $super->fresh()->role,
            'Menurunkan peran sendiri akan mengunci diri keluar dari seluruh sistem');
    }

    public function test_perintah_server_memulihkan_akun_superadministrator(): void
    {
        // Peran ini tidak bisa diberikan lewat pendaftaran atau formulir tambah
        // user, jadi tanpa jalur server sekali hilang berarti terkunci selamanya.
        $this->artisan('drive:superadmin', [
            '--username' => 'aldeftech',
            '--password' => 'rahasia12345',
        ])->assertSuccessful();

        $baru = User::where('username', 'aldeftech')->firstOrFail();

        $this->assertSame(User::ROLE_SUPERADMIN, $baru->role);
        $this->assertTrue($baru->is_active);
        $this->assertNull($baru->company_id, 'Superadmin tidak terikat perusahaan mana pun');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('rahasia12345', $baru->password));
        $this->assertNotNull($baru->api_token, 'Harus bisa langsung masuk lewat aplikasi juga');
    }

    public function test_perintah_server_mengangkat_kembali_akun_yang_turun_peran(): void
    {
        $company = $this->company('PT Turun');
        $korban = $this->user($company, User::ROLE_USER, ['username' => 'aldeftech', 'is_active' => false]);

        $this->artisan('drive:superadmin', [
            '--username' => 'aldeftech',
            '--password' => 'rahasia12345',
        ])->assertSuccessful();

        $pulih = $korban->fresh();

        $this->assertSame(User::ROLE_SUPERADMIN, $pulih->role);
        $this->assertTrue($pulih->is_active, 'Akun nonaktif ikut diaktifkan kembali');
        $this->assertNull($pulih->company_id);
        $this->assertSame($korban->id, $pulih->id, 'Akun yang sama, bukan akun baru');
    }

    public function test_perintah_server_menolak_password_lemah(): void
    {
        $this->artisan('drive:superadmin', [
            '--username' => 'aldeftech',
            '--password' => 'pendek',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['username' => 'aldeftech']);
    }

    // ------------------------------------ Notifikasi tidak lintas perusahaan

    public function test_notifikasi_aktivitas_tidak_bocor_ke_admin_perusahaan_lain(): void
    {
        $a = $this->company('PT Alpha Notif');
        $b = $this->company('PT Beta Notif');

        $anggotaA = $this->user($a, User::ROLE_USER, ['name' => 'Anggota Alpha']);
        $adminA = $this->user($a, User::ROLE_ADMIN);
        $adminB = $this->user($b, User::ROLE_ADMIN);
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->actingAs($anggotaA)->put('/profile', [
            'name' => 'Anggota Alpha Baru',
            'email' => $anggotaA->email,
        ]);

        $punya = fn (User $u) => \App\Models\Notification::where('user_id', $u->id)
            ->where('type', 'profile_updated')->exists();

        $this->assertTrue($punya($adminA), 'Admin perusahaan yang sama harus tahu');
        $this->assertTrue($punya($super), 'Superadministrator mengawasi seluruh perusahaan');
        $this->assertFalse($punya($adminB),
            'Aktivitas satu perusahaan bukan urusan admin perusahaan lain');
    }

    public function test_penghapusan_file_hanya_memberi_tahu_admin_perusahaannya(): void
    {
        $a = $this->company('PT Hapus A');
        $b = $this->company('PT Hapus B');

        $anggotaA = $this->user($a);
        $adminA = $this->user($a, User::ROLE_ADMIN);
        $adminB = $this->user($b, User::ROLE_ADMIN);

        $file = \App\Models\File::create([
            'user_id' => $anggotaA->id,
            'name' => 'berkas.txt',
            'original_name' => 'berkas.txt',
            'path' => 'drive/berkas.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'folder' => '/',
            'is_locked' => false,
            'is_hidden' => false,
        ]);

        $this->actingAs($anggotaA)->delete('/drive/file/' . $file->id);

        $punya = fn (User $u) => \App\Models\Notification::where('user_id', $u->id)
            ->where('type', 'file_deleted')->exists();

        $this->assertTrue($punya($adminA));
        $this->assertFalse($punya($adminB));
    }

    // ---------------------------- Admin perusahaan menambah akun sendiri

    public function test_admin_perusahaan_bisa_menambah_akun_di_perusahaannya(): void
    {
        $company = $this->company('PT Rekrut Sendiri', ['default_quota' => 2 * 1073741824]);
        $admin = $this->user($company, User::ROLE_ADMIN);

        $this->actingAs($admin)->get('/admin/users/create')
            ->assertOk()
            ->assertSee('PT Rekrut Sendiri');

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Anggota Baru',
            'email' => 'anggotabaru@aldeftech.test',
            'company_id' => $company->id,
            'role' => User::ROLE_USER,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect(route('admin.users'))->assertSessionHas('success');

        $baru = User::where('email', 'anggotabaru@aldeftech.test')->firstOrFail();

        $this->assertSame($company->id, $baru->company_id);
        $this->assertTrue($baru->is_active);
        $this->assertSame(2 * 1073741824, $baru->storage_quota);
    }

    public function test_admin_tidak_bisa_menambah_akun_ke_perusahaan_lain(): void
    {
        $milik = $this->company('PT Milik Saya');
        $orang = $this->company('PT Orang Lain');
        $admin = $this->user($milik, User::ROLE_ADMIN);

        // Formulirnya mengunci perusahaan, tetapi isian bisa diubah sebelum
        // dikirim - jadi servernya yang harus menolak, bukan tampilannya.
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Selundupan',
            'email' => 'selundup@aldeftech.test',
            'company_id' => $orang->id,
            'role' => User::ROLE_USER,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect();

        $baru = User::where('email', 'selundup@aldeftech.test')->firstOrFail();

        $this->assertSame($milik->id, $baru->company_id,
            'Akun harus tetap masuk ke perusahaan admin yang membuatnya');
    }

    public function test_formulir_tambah_user_mengunci_perusahaan_bagi_admin(): void
    {
        $company = $this->company('PT Terkunci');
        $admin = $this->user($company, User::ROLE_ADMIN);
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $this->company('PT Lainnya');

        $html = $this->actingAs($admin)->get('/admin/users/create')->assertOk()->getContent();
        $this->assertStringNotContainsString('PT Lainnya', $html,
            'Admin perusahaan tidak boleh melihat perusahaan lain sebagai pilihan');

        $this->app['auth']->forgetGuards();

        $html = $this->actingAs($super)->get('/admin/users/create')->assertOk()->getContent();
        $this->assertStringContainsString('PT Lainnya', $html,
            'Superadministrator tetap memilih dari seluruh perusahaan');
    }

    public function test_pengguna_biasa_tetap_tidak_bisa_menambah_akun(): void
    {
        $biasa = $this->user($this->company('PT Bukan Admin'));

        $this->actingAs($biasa)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($biasa)->post('/admin/users', [
            'name' => 'Nekat',
            'email' => 'nekat@aldeftech.test',
            'company_id' => $biasa->company_id,
            'role' => User::ROLE_ADMIN,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nekat@aldeftech.test']);
    }

    // ------------------------------------------- Kontrak API aplikasi

    /** Kepala permintaan untuk pengguna yang sudah punya token. */
    private function bearer(User $user): array
    {
        if (!$user->api_token) {
            $user->update(['api_token' => \Illuminate\Support\Str::random(64)]);
        }

        return [
            'Authorization' => 'Bearer ' . $user->fresh()->api_token,
            'Accept' => 'application/json',
        ];
    }

    public function test_api_companies_memberi_data_yang_dibutuhkan_form_daftar(): void
    {
        $this->company('PT Alpha', ['default_quota' => 3 * 1073741824]);
        $this->company('PT Mati', ['is_active' => false]);

        $res = $this->withHeaders(['Accept' => 'application/json'])
            ->get('/api/companies')->assertOk()->json();

        $nama = array_column($res['companies'], 'name');
        $this->assertContains('PT Alpha', $nama);
        $this->assertNotContains('PT Mati', $nama, 'Perusahaan nonaktif tidak boleh bisa dipilih');

        // Aplikasi menampilkan logo dan kuota di daftar pilihan; tanpa kunci
        // ini barisnya kosong tanpa pesan error apa pun.
        $alpha = collect($res['companies'])->firstWhere('name', 'PT Alpha');
        $this->assertArrayHasKey('logo', $alpha);
        $this->assertArrayHasKey('quota_gb', $alpha);
        $this->assertSame('3', $alpha['quota_gb']);
    }

    public function test_api_login_dan_me_membawa_identitas_perusahaan(): void
    {
        $company = $this->company('PT Identitas');
        $user = $this->user($company, User::ROLE_USER, ['is_active' => true]);
        $user->update(['password' => \Illuminate\Support\Facades\Hash::make('rahasia12345')]);

        $login = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/login', ['email' => $user->email, 'password' => 'rahasia12345'])
            ->assertOk()->json();

        foreach (['role', 'role_label', 'is_superadmin', 'company'] as $kunci) {
            $this->assertArrayHasKey($kunci, $login['user'], "Kunci {$kunci} dipakai aplikasi");
        }

        $this->assertSame('PT Identitas', $login['user']['company']['name']);
        $this->assertArrayHasKey('logo', $login['user']['company']);

        $me = $this->withHeaders($this->bearer($user))->get('/api/me')->assertOk()->json();

        $this->assertSame('PT Identitas', $me['user']['company']['name']);
        $this->assertArrayHasKey('logo', $me['user']['company']);
    }

    public function test_api_admin_users_menyebut_peran_dan_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $this->user($this->company('PT Satu'), User::ROLE_ADMIN, ['name' => 'Admin Satu']);

        $res = $this->withHeaders($this->bearer($super))
            ->get('/api/admin/users')->assertOk()->json();

        $adminSatu = collect($res['users'])->firstWhere('name', 'Admin Satu');

        $this->assertSame('PT Satu', $adminSatu['company'],
            'Superadmin melihat lintas perusahaan, jadi asalnya harus jelas');
        $this->assertSame('Admin', $adminSatu['role_label']);
    }

    public function test_api_hidden_keyword_hanya_membuka_nilainya_untuk_superadmin(): void
    {
        \App\Models\Setting::setHiddenKeyword('kunciAplikasi9');

        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $admin = $this->user($this->company('PT Rahasia'), User::ROLE_ADMIN);

        $res = $this->withHeaders($this->bearer($super))
            ->get('/api/admin/hidden-keyword')->assertOk()->json();

        $this->assertTrue($res['can_reveal']);
        $this->assertSame('kunciAplikasi9', $res['keyword']);

        // Guard menyimpan pengguna yang sudah terselesaikan; di produksi tiap
        // permintaan memakai container baru, di pengujian tidak.
        $this->app['auth']->forgetGuards();

        $res = $this->withHeaders($this->bearer($admin))
            ->get('/api/admin/hidden-keyword')->assertOk()->json();

        $this->assertFalse($res['can_reveal']);
        $this->assertNull($res['keyword'], 'Admin perusahaan tidak boleh melihat nilainya');
    }

    public function test_api_register_menolak_pendaftaran_tanpa_perusahaan(): void
    {
        // Aplikasi versi lama tidak mengirim company_id; pastikan penolakannya
        // jelas, bukan error server.
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/register', [
                'name' => 'Tanpa Perusahaan',
                'email' => 'tanpa@aldeftech.test',
                'password' => 'rahasia12345',
                'password_confirmation' => 'rahasia12345',
            ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'tanpa@aldeftech.test']);
    }

    public function test_api_register_lewat_aplikasi_mengikat_ke_perusahaan(): void
    {
        $company = $this->company('PT Aplikasi', ['default_quota' => 2 * 1073741824]);

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/register', [
                'name' => 'Pendaftar App',
                'email' => 'app@aldeftech.test',
                'password' => 'rahasia12345',
                'password_confirmation' => 'rahasia12345',
                'company_id' => $company->id,
            ])->assertOk()->assertJson(['success' => true]);

        $baru = User::where('email', 'app@aldeftech.test')->firstOrFail();

        $this->assertSame($company->id, $baru->company_id);
        $this->assertFalse($baru->is_active, 'Pendaftar aplikasi tetap menunggu verifikasi');
        $this->assertSame(2 * 1073741824, $baru->storage_quota);
    }

    // --------------------------------- API superadministrator (aplikasi)

    public function test_api_login_menerima_username_selain_email(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN, ['username' => 'superuji']);
        $super->update(['password' => \Illuminate\Support\Facades\Hash::make('rahasia12345')]);

        // Superadministrator tidak punya email perusahaan; tanpa dukungan
        // username ia tidak bisa masuk lewat aplikasi sama sekali.
        $res = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/login', ['email' => 'superuji', 'password' => 'rahasia12345'])
            ->assertOk()->json();

        $this->assertTrue($res['user']['is_superadmin']);

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/login', ['email' => 'superuji', 'password' => 'salah'])
            ->assertStatus(401);
    }

    public function test_api_login_menutup_akses_saat_perusahaan_nonaktif(): void
    {
        $company = $this->company('PT Tutup');
        $user = $this->user($company, User::ROLE_USER, ['is_active' => true]);
        $user->update(['password' => \Illuminate\Support\Facades\Hash::make('rahasia12345')]);

        $company->update(['is_active' => false]);

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/login', ['email' => $user->email, 'password' => 'rahasia12345'])
            ->assertStatus(403);
    }

    public function test_api_perusahaan_hanya_bisa_dikelola_superadmin(): void
    {
        $company = $this->company('PT Jaga');
        $admin = $this->user($company, User::ROLE_ADMIN);

        $this->withHeaders($this->bearer($admin))
            ->get('/api/admin/companies')->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->withHeaders($this->bearer($admin))
            ->post('/api/admin/companies', ['name' => 'PT Selundupan', 'default_quota_gb' => 1])
            ->assertForbidden();

        $this->assertDatabaseMissing('companies', ['name' => 'PT Selundupan']);
    }

    public function test_api_superadmin_bisa_crud_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        // Buat
        $this->withHeaders($this->bearer($super))->post('/api/admin/companies', [
            'name' => 'PT Aplikasi Baru',
            'email' => 'kontak@aplikasi.test',
            'default_quota_gb' => 5,
            'is_active' => true,
        ])->assertOk()->assertJson(['success' => true]);

        $company = Company::where('name', 'PT Aplikasi Baru')->firstOrFail();
        $this->assertSame(5 * 1073741824, $company->default_quota);

        // Ubah
        $this->withHeaders($this->bearer($super))
            ->post('/api/admin/companies/' . $company->id, [
                'name' => 'PT Aplikasi Baru',
                'default_quota_gb' => 8,
                'is_active' => true,
            ])->assertOk();

        $this->assertSame(8 * 1073741824, $company->fresh()->default_quota);

        // Nonaktifkan: penggunanya ikut kehilangan akses
        $anggota = $this->user($company);
        $this->withHeaders($this->bearer($super))
            ->post('/api/admin/companies/' . $company->id . '/toggle')->assertOk();

        $this->assertFalse($company->fresh()->is_active);
        $this->assertFalse($anggota->fresh()->is_active);

        // Berpenghuni tidak boleh dihapus
        $this->withHeaders($this->bearer($super))
            ->delete('/api/admin/companies/' . $company->id)->assertStatus(422);

        $anggota->delete();

        $this->withHeaders($this->bearer($super))
            ->delete('/api/admin/companies/' . $company->id)->assertOk();

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function test_api_kuota_bisa_diatur_satuan_dan_massal(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $a = $this->company('PT Kuota A');
        $b = $this->company('PT Kuota B');

        $satu = $this->user($a);
        $dua = $this->user($a);
        $lain = $this->user($b);

        $this->withHeaders($this->bearer($super))
            ->put('/api/admin/quotas/' . $satu->id, ['quota_gb' => 7])->assertOk();

        $this->assertSame(7 * 1073741824, $satu->fresh()->storage_quota);
        $this->assertDatabaseHas('notifications', ['user_id' => $satu->id, 'type' => 'quota_changed']);

        $this->withHeaders($this->bearer($super))->post('/api/admin/quotas/bulk', [
            'quota_gb' => 3,
            'target' => 'company',
            'company_id' => $a->id,
        ])->assertOk();

        $this->assertSame(3 * 1073741824, $satu->fresh()->storage_quota);
        $this->assertSame(3 * 1073741824, $dua->fresh()->storage_quota);
        $this->assertSame(User::DEFAULT_STORAGE_QUOTA, $lain->fresh()->storage_quota,
            'Perusahaan lain tidak boleh ikut berubah');
    }

    public function test_api_superadmin_membuat_akun_yang_langsung_aktif(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Rekrut', ['default_quota' => 4 * 1073741824]);

        $this->withHeaders($this->bearer($super))->post('/api/admin/create-user', [
            'name' => 'Dari Aplikasi',
            'email' => 'dariapp@aldeftech.test',
            'company_id' => $company->id,
            'role' => User::ROLE_ADMIN,
            'password' => 'rahasia12345',
        ])->assertOk()->assertJson(['success' => true]);

        $baru = User::where('email', 'dariapp@aldeftech.test')->firstOrFail();

        $this->assertTrue($baru->is_active);
        $this->assertSame(User::ROLE_ADMIN, $baru->role);
        $this->assertSame(4 * 1073741824, $baru->storage_quota);
        $this->assertNotNull($baru->api_token, 'Akun baru harus bisa langsung masuk lewat aplikasi');
    }

    public function test_api_tidak_bisa_membuat_superadmin_baru(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Coba API');

        $this->withHeaders($this->bearer($super))->post('/api/admin/create-user', [
            'name' => 'Calon Super',
            'email' => 'calonsuperapi@aldeftech.test',
            'company_id' => $company->id,
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'rahasia12345',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'calonsuperapi@aldeftech.test']);
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
            ->assertSee('aldef-logo.png');

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

    public function test_sidebar_superadmin_memakai_logo_aldef(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->actingAs($super)->get('/drive')
            ->assertOk()
            ->assertSee('aldef-logo.png')
            ->assertSee('Superadministrator');

        $this->assertFileExists(public_path('aldef-logo.png'),
            'Berkas logonya harus ikut terkirim, bukan hanya dirujuk');
    }

    public function test_penanda_superadministrator_tidak_bocor_ke_peran_lain(): void
    {
        $company = $this->company('PT Biasa');

        // Logo Aldef Tech kini identitas produknya, jadi wajar terlihat semua
        // orang. Yang menandai peran tertinggi adalah labelnya - itu yang tidak
        // boleh muncul di sidebar orang lain.
        foreach ([User::ROLE_ADMIN, User::ROLE_USER] as $peran) {
            $this->app['auth']->forgetGuards();

            $this->actingAs($this->user($company, $peran))->get('/drive')
                ->assertOk()
                ->assertSee('aldef-logo.png')
                ->assertDontSee('Superadministrator');
        }
    }

    public function test_logo_perusahaan_menggantikan_identitas_bawaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->actingAs($super)->post('/admin/companies', [
            'name' => 'PT Berlogo Sendiri',
            'default_quota_gb' => 1,
            'is_active' => 1,
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 100, 100),
        ]);

        $company = Company::where('name', 'PT Berlogo Sendiri')->firstOrFail();
        $anggota = $this->user($company);

        $this->app['auth']->forgetGuards();

        // Perusahaan yang punya logo sendiri memakai logonya, bukan Aldef Tech.
        $this->actingAs($anggota)->get('/drive')
            ->assertOk()
            ->assertSee('/company-logo/' . $company->id)
            ->assertDontSee('aldef-logo.png');

        $this->bersihkanLogo();
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
            'email' => 'karyawan@aldeftech.test',
            'company_id' => $company->id,
            'role' => User::ROLE_USER,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect(route('admin.users'))->assertSessionHas('success');

        $baru = User::where('email', 'karyawan@aldeftech.test')->firstOrFail();

        $this->assertTrue($baru->is_active, 'Akun buatan superadmin tidak perlu diverifikasi lagi');
        $this->assertSame(User::ROLE_USER, $baru->role);
        $this->assertSame($company->id, $baru->company_id);
        $this->assertSame(3 * 1073741824, $baru->storage_quota, 'Kuota mengikuti perusahaan');

        // Langsung bisa dipakai, tanpa langkah tambahan apa pun.
        $this->post('/logout');
        $this->assertTrue(auth()->attempt([
            'email' => 'karyawan@aldeftech.test',
            'password' => 'rahasia12345',
        ]));
    }

    public function test_superadmin_bisa_membuat_akun_berperan_admin(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Kelola');

        $this->actingAs($super)->post('/admin/users', [
            'name' => 'Admin Baru',
            'email' => 'adminbaru2@aldeftech.test',
            'company_id' => $company->id,
            'role' => User::ROLE_ADMIN,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect(route('admin.users'));

        $admin = User::where('email', 'adminbaru2@aldeftech.test')->firstOrFail();

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
            'email' => 'calonsuper@aldeftech.test',
            'company_id' => $company->id,
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'calonsuper@aldeftech.test']);
    }

    public function test_tombol_tambah_user_terbuka_untuk_admin_tapi_bukan_pengguna_biasa(): void
    {
        $company = $this->company('PT Batas');
        $admin = $this->user($company, User::ROLE_ADMIN);
        $biasa = $this->user($company);

        // Admin perusahaan kini boleh menambah akun - terbatas pada
        // perusahaannya sendiri, yang dijaga di pengujian terpisah.
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Tambah User');
        $this->actingAs($admin)->get('/admin/users/create')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->actingAs($biasa)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($biasa)->post('/admin/users', [
            'name' => 'Selundupan',
            'email' => 'selundupan@aldeftech.test',
            'company_id' => $company->id,
            'role' => User::ROLE_ADMIN,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'selundupan@aldeftech.test']);
    }

    public function test_akun_baru_ditolak_bila_perusahaan_penuh_atau_nonaktif(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $penuh = $this->company('PT Penuh', ['max_users' => 1]);
        $this->user($penuh);

        $this->actingAs($super)->post('/admin/users', [
            'name' => 'Kelebihan',
            'email' => 'kelebihan@aldeftech.test',
            'company_id' => $penuh->id,
            'role' => User::ROLE_USER,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('company_id');

        $mati = $this->company('PT Mati', ['is_active' => false]);

        $this->actingAs($super)->post('/admin/users', [
            'name' => 'Tetap Ditolak',
            'email' => 'ditolak@aldeftech.test',
            'company_id' => $mati->id,
            'role' => User::ROLE_USER,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('company_id');

        $this->assertDatabaseMissing('users', ['email' => 'kelebihan@aldeftech.test']);
        $this->assertDatabaseMissing('users', ['email' => 'ditolak@aldeftech.test']);
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
            'email' => 'calon@aldeftech.test',
            'company_id' => $company->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect(route('register'));

        $baru = User::where('email', 'calon@aldeftech.test')->firstOrFail();

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
            'email' => 'terlambat@aldeftech.test',
            'company_id' => $company->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('company_id');

        $this->assertDatabaseMissing('users', ['email' => 'terlambat@aldeftech.test']);
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
            'email' => 'calona@aldeftech.test',
            'company_id' => $a->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ]);

        $this->assertDatabaseHas('notifications', ['user_id' => $adminA->id, 'type' => 'new_registration']);
        $this->assertDatabaseHas('notifications', ['user_id' => $super->id, 'type' => 'new_registration']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $adminB->id, 'type' => 'new_registration']);
    }
}
