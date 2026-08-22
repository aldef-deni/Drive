<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\File;
use App\Models\FileFolder;
use App\Models\FileShare;
use App\Models\Setting;
use App\Models\User;
use App\Services\FileEncryptionService;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DriveFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Bersihkan berkas fisik yang dibuat selama pengujian.
        $dir = storage_path('app/drive');
        if (is_dir($dir)) {
            $this->deleteTree($dir);
        }

        parent::tearDown();
    }

    private function deleteTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /** Perusahaan uji, dibuat sekali lalu dipakai ulang. */
    private function company(): Company
    {
        return Company::firstOrCreate(
            ['slug' => 'perusahaan-uji'],
            ['name' => 'Perusahaan Uji', 'default_quota' => User::DEFAULT_STORAGE_QUOTA, 'is_active' => true]
        );
    }

    private function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'company_id' => $this->company()->id,
            'name' => 'Pengguna Uji',
            'email' => 'user' . uniqid() . '@dekorasi.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'storage_quota' => 104857600,
            'storage_used' => 0,
            'is_active' => true,
        ], $attributes));
    }

    // ---------------------------------------------------------------
    // Autentikasi
    // ---------------------------------------------------------------

    public function test_registrasi_membuat_akun_non_aktif_dan_belum_bisa_login(): void
    {
        $this->post('/register', [
            'name' => 'Budi',
            'email' => 'budi@dekorasi.test',
            'company_id' => $this->company()->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertRedirect(route('register'));

        $user = User::where('email', 'budi@dekorasi.test')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_active, 'Akun baru harus menunggu aktivasi admin');

        $this->post('/login', ['email' => 'budi@dekorasi.test', 'password' => 'rahasia12345'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_berhasil_setelah_akun_diaktifkan(): void
    {
        $user = $this->makeUser(['email' => 'aktif@dekorasi.test']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect('/drive');

        $this->assertAuthenticatedAs($user);
    }

    public function test_halaman_privat_menolak_tamu(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/notifications')->assertRedirect('/login');
        $this->get('/drive')->assertRedirect('/login');
        $this->post('/logout')->assertRedirect('/login');
    }

    // ---------------------------------------------------------------
    // Drive
    // ---------------------------------------------------------------

    public function test_halaman_drive_tampil_untuk_user_login(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/drive')->assertOk();
        $response->assertSee('Drive Saya');

        // Regresi: blok <script> pernah tertutup terlalu awal sehingga sisa kode JS
        // ikut tampil sebagai teks biasa di halaman.
        $html = $response->getContent();
        $this->assertSame(
            substr_count($html, '<script'),
            substr_count($html, '</script>'),
            'Jumlah tag <script> dan </script> harus seimbang'
        );

        $tanpaScript = preg_replace('#<script[^>]*>.*?</script>#is', '', $html);
        $this->assertStringNotContainsString(
            'function openFilePreview',
            $tanpaScript,
            'Kode JavaScript tidak boleh bocor ke luar tag <script>'
        );
    }

    public function test_unggah_membuat_file_dan_menambah_pemakaian_storage(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post('/drive/upload', [
            'file' => UploadedFile::fake()->create('Laporan Akhir 2024.pdf', 12, 'application/pdf'),
            'folder' => '/',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $file = File::where('user_id', $user->id)->first();
        $this->assertNotNull($file);
        $this->assertSame('Laporan Akhir 2024.pdf', $file->original_name);
        $this->assertStringNotContainsString(' ', $file->path, 'Nama fisik file harus dibersihkan');
        $this->assertFileExists(storage_path('app/drive/' . $file->path));
        $this->assertGreaterThan(0, $user->fresh()->storage_used);
    }

    public function test_kunci_file_menyimpan_hash_bukan_password_polos(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/drive/upload', [
            'file' => UploadedFile::fake()->create('rahasia.txt', 4, 'text/plain'),
            'folder' => '/',
            'is_locked' => 1,
            'lock_password' => 'kunci1234',
        ])->assertOk();

        $file = File::where('user_id', $user->id)->firstOrFail();

        $this->assertTrue($file->is_encrypted);
        $this->assertNull($file->encryption_password, 'Password tidak boleh disimpan polos');
        $this->assertTrue(Hash::check('kunci1234', $file->lock_password));

        // File terkunci tidak bisa dihapus
        $this->actingAs($user)->deleteJson('/drive/file/' . $file->id)
            ->assertStatus(403)
            ->assertJson(['success' => false]);

        // Password salah ditolak
        $this->actingAs($user)->postJson('/drive/file/' . $file->id . '/unlock', ['password' => 'salah'])
            ->assertStatus(400);

        // Password benar membuka kunci sekaligus mendekripsi
        $this->actingAs($user)->postJson('/drive/file/' . $file->id . '/unlock', ['password' => 'kunci1234'])
            ->assertOk()->assertJson(['success' => true]);

        $file->refresh();
        $this->assertFalse($file->is_encrypted);
        $this->assertNull($file->lock_password);
        $this->assertFileExists(storage_path('app/drive/' . $file->path));
    }

    public function test_enkripsi_dan_dekripsi_bolak_balik(): void
    {
        $service = app(FileEncryptionService::class);
        $isi = 'Konten rahasia Dekorasi Drive — 12345';

        $terenkripsi = $service->encrypt($isi, 'kunci-uji');

        $this->assertNotSame($isi, $terenkripsi);
        $this->assertSame($isi, $service->decrypt($terenkripsi, 'kunci-uji'));

        $this->expectException(\RuntimeException::class);
        $service->decrypt($terenkripsi, 'password-salah');
    }

    public function test_password_salah_selalu_ditolak_bukan_menghasilkan_file_rusak(): void
    {
        $service = app(FileEncryptionService::class);
        $isi = 'Dokumen rahasia Dekorasi Drive';
        $terenkripsi = $service->encrypt($isi, 'kunci-benar');

        // Regresi: tanpa tanda tangan HMAC, dekripsi AES-CBC dengan kunci salah
        // punya peluang sekitar 1/256 menghasilkan padding yang kebetulan sah,
        // sehingga mengembalikan data rusak alih-alih menolak. 400 percobaan
        // membuat kebocoran semacam itu hampir pasti tertangkap.
        for ($i = 0; $i < 400; $i++) {
            try {
                $service->decrypt($terenkripsi, 'salah-' . $i);
                $this->fail('Password salah ke-' . $i . ' seharusnya ditolak');
            } catch (\RuntimeException $e) {
                // benar: ditolak
            }
        }

        $this->assertSame($isi, $service->decrypt($terenkripsi, 'kunci-benar'));
    }

    public function test_file_terenkripsi_format_lama_tetap_bisa_dibuka(): void
    {
        $service = app(FileEncryptionService::class);
        $isi = 'Berkas lama sebelum format v3';
        $password = 'kunci-lama';

        // Bentuk ulang payload format v2 (tanpa HMAC) persis seperti yang
        // tersimpan di server sebelum perubahan ini.
        $kunci = hash('sha256', $password . (string) config('app.key'), true);
        $iv = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($isi, 'AES-256-CBC', $kunci, 0, $iv);
        $lama = base64_encode('v2:' . bin2hex($iv) . '::' . $cipher);

        $this->assertSame($isi, $service->decrypt($lama, $password));
    }

    public function test_pencarian_tidak_membocorkan_file_tersembunyi(): void
    {
        $user = $this->makeUser();

        File::create([
            'user_id' => $user->id,
            'name' => 'rahasia.txt',
            'original_name' => 'dokumen-rahasia.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'path' => $user->id . '/rahasia.txt',
            'folder' => '/',
            'is_hidden' => true,
        ]);

        $this->actingAs($user)->get('/drive?search=rahasia')
            ->assertOk()
            ->assertDontSee('dokumen-rahasia.txt');
    }

    public function test_folder_dengan_awalan_nama_sama_tidak_ikut_terhapus(): void
    {
        $user = $this->makeUser();

        $foto = FileFolder::create(['user_id' => $user->id, 'name' => 'Foto', 'path' => '/Foto', 'parent_path' => '/']);
        FileFolder::create(['user_id' => $user->id, 'name' => 'Foto Lama', 'path' => '/Foto Lama', 'parent_path' => '/']);

        $fileTetangga = File::create([
            'user_id' => $user->id,
            'name' => 'a.txt',
            'original_name' => 'a.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
            'path' => $user->id . '/a.txt',
            'folder' => '/Foto Lama',
        ]);

        $this->actingAs($user)->deleteJson('/drive/folder/' . $foto->id)->assertOk();

        $this->assertDatabaseHas('file_folders', ['path' => '/Foto Lama']);
        $this->assertDatabaseHas('files', ['id' => $fileTetangga->id]);
    }

    public function test_pindah_folder_memperbarui_path_anak(): void
    {
        $user = $this->makeUser();

        $induk = FileFolder::create(['user_id' => $user->id, 'name' => 'Arsip', 'path' => '/Arsip', 'parent_path' => '/']);
        $anak = FileFolder::create(['user_id' => $user->id, 'name' => 'Doc', 'path' => '/Doc', 'parent_path' => '/']);
        FileFolder::create(['user_id' => $user->id, 'name' => 'Sub', 'path' => '/Doc/Sub', 'parent_path' => '/Doc']);

        $this->actingAs($user)
            ->postJson('/drive/folder/' . $anak->id . '/move', ['parent_path' => $induk->path])
            ->assertOk();

        $this->assertDatabaseHas('file_folders', ['id' => $anak->id, 'path' => '/Arsip/Doc', 'parent_path' => '/Arsip']);
        $this->assertDatabaseHas('file_folders', ['path' => '/Arsip/Doc/Sub', 'parent_path' => '/Arsip/Doc']);
    }

    public function test_user_lain_tidak_bisa_mengakses_file_orang(): void
    {
        $pemilik = $this->makeUser();
        $penyusup = $this->makeUser();

        $file = File::create([
            'user_id' => $pemilik->id,
            'name' => 'x.txt',
            'original_name' => 'x.txt',
            'mime_type' => 'text/plain',
            'size' => 5,
            'path' => $pemilik->id . '/x.txt',
            'folder' => '/',
        ]);

        $this->actingAs($penyusup)->get('/drive/file/' . $file->id . '/download')->assertForbidden();
        $this->actingAs($penyusup)->deleteJson('/drive/file/' . $file->id)->assertForbidden();
        $this->actingAs($penyusup)->postJson('/drive/file/' . $file->id . '/share')->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Hidden system (kata kunci rahasia)
    // ---------------------------------------------------------------

    private function makeHiddenFile(User $user, string $name = 'file-tersembunyi.txt'): File
    {
        return File::create([
            'user_id' => $user->id,
            'name' => 'h.txt',
            'original_name' => $name,
            'mime_type' => 'text/plain',
            'size' => 5,
            'path' => $user->id . '/h.txt',
            'folder' => '/',
            'is_hidden' => true,
        ]);
    }

    public function test_item_tersembunyi_tidak_muncul_di_drive_maupun_pencarian(): void
    {
        $user = $this->makeUser();
        $this->makeHiddenFile($user);

        $this->actingAs($user)->get('/drive')
            ->assertOk()
            ->assertDontSee('file-tersembunyi.txt');

        $this->actingAs($user)->get('/drive?search=tersembunyi')
            ->assertOk()
            ->assertDontSee('file-tersembunyi.txt');
    }

    public function test_kata_kunci_bawaan_memunculkan_item_tersembunyi(): void
    {
        $user = $this->makeUser();
        $this->makeHiddenFile($user);

        // Mengetik kata kunci mengalihkan kembali ke drive dengan mode ungkap aktif.
        $this->actingAs($user)->get('/drive?search=deniafrizal')
            ->assertRedirect(route('drive.index', ['folder' => '/']))
            ->assertSessionHas('hidden_revealed', true);

        $this->actingAs($user)->withSession(['hidden_revealed' => true])->get('/drive')
            ->assertOk()
            ->assertSee('file-tersembunyi.txt')
            ->assertSee('Mode rahasia aktif');
    }

    public function test_mode_ungkap_bisa_ditutup_kembali(): void
    {
        $user = $this->makeUser();
        $this->makeHiddenFile($user);

        $this->actingAs($user)->withSession(['hidden_revealed' => true])
            ->post('/drive/reveal/off', ['folder' => '/'])
            ->assertRedirect()
            ->assertSessionMissing('hidden_revealed');

        $this->actingAs($user)->get('/drive')->assertOk()->assertDontSee('file-tersembunyi.txt');
    }

    public function test_kata_kunci_salah_diperlakukan_sebagai_pencarian_biasa(): void
    {
        $user = $this->makeUser();
        $this->makeHiddenFile($user);

        $this->actingAs($user)->get('/drive?search=deniafrizal2')
            ->assertOk()
            ->assertSessionMissing('hidden_revealed')
            ->assertDontSee('file-tersembunyi.txt');
    }

    public function test_admin_bisa_mengganti_kata_kunci_rahasia(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/hidden-system')
            ->assertOk()
            ->assertSee('Kata Kunci Rahasia')
            ->assertSee('Masih memakai kata kunci bawaan');

        // Password admin salah -> ditolak
        $this->actingAs($admin)->put('/admin/hidden-system', [
            'current_password' => 'bukan-password',
            'keyword' => 'kunciBaru123',
            'keyword_confirmation' => 'kunciBaru123',
        ])->assertSessionHasErrors('current_password');

        // Konfirmasi tidak sama -> ditolak
        $this->actingAs($admin)->put('/admin/hidden-system', [
            'current_password' => 'password123',
            'keyword' => 'kunciBaru123',
            'keyword_confirmation' => 'beda',
        ])->assertSessionHasErrors('keyword');

        // Benar -> tersimpan
        $this->actingAs($admin)->put('/admin/hidden-system', [
            'current_password' => 'password123',
            'keyword' => 'kunciBaru123',
            'keyword_confirmation' => 'kunciBaru123',
        ])->assertRedirect(route('admin.hidden'))->assertSessionHas('success');

        $this->assertTrue(Setting::matchesHiddenKeyword('kunciBaru123'));
        $this->assertFalse(Setting::matchesHiddenKeyword('deniafrizal'), 'Kata kunci bawaan harus berhenti berlaku');
    }

    public function test_kata_kunci_baru_dipakai_untuk_mengungkap(): void
    {
        Setting::setHiddenKeyword('bukaRahasia');

        $user = $this->makeUser();
        $this->makeHiddenFile($user);

        // Kata kunci lama tidak lagi berlaku
        $this->actingAs($user)->get('/drive?search=deniafrizal')
            ->assertOk()
            ->assertSessionMissing('hidden_revealed');

        $this->actingAs($user)->get('/drive?search=bukaRahasia')
            ->assertRedirect()
            ->assertSessionHas('hidden_revealed', true);
    }

    public function test_kata_kunci_tidak_pernah_tersimpan_apa_adanya(): void
    {
        Setting::setHiddenKeyword('rahasiaKu99');

        $tersimpan = Setting::get(Setting::HIDDEN_KEYWORD);

        $this->assertNotSame('rahasiaKu99', $tersimpan);
        $this->assertStringNotContainsString('rahasiaKu99', (string) $tersimpan);
        $this->assertTrue(Setting::matchesHiddenKeyword('rahasiaKu99'));
    }

    public function test_kata_kunci_aktif_bisa_dibaca_kembali(): void
    {
        // Nilai bawaan berlaku selama belum pernah diganti.
        $this->assertSame(Setting::DEFAULT_HIDDEN_KEYWORD, Setting::hiddenKeywordPlain());

        Setting::setHiddenKeyword('rahasiaKu99');

        $this->assertSame('rahasiaKu99', Setting::hiddenKeywordPlain());
    }

    public function test_kata_kunci_hash_versi_lama_tetap_berlaku_tapi_tidak_terbaca(): void
    {
        // Simulasi data lama: nilainya hash bcrypt, bukan hasil enkripsi.
        Setting::put(Setting::HIDDEN_KEYWORD, Hash::make('kunciLama77'));

        $this->assertTrue(Setting::matchesHiddenKeyword('kunciLama77'),
            'Kata kunci lama harus tetap membuka file tersembunyi');
        $this->assertFalse(Setting::matchesHiddenKeyword('kunciSalah'));
        $this->assertNull(Setting::hiddenKeywordPlain(),
            'Hash searah tidak boleh dipaksa dibaca');

        // Setelah diganti sekali, nilainya bisa ditampilkan lagi.
        Setting::setHiddenKeyword('kunciBaru88');
        $this->assertSame('kunciBaru88', Setting::hiddenKeywordPlain());
    }

    public function test_superadmin_melihat_kata_kunci_aktif_di_menu_hidden_system(): void
    {
        Setting::setHiddenKeyword('kunciTampil42');

        $super = $this->makeUser(['role' => User::ROLE_SUPERADMIN, 'company_id' => null]);

        $this->actingAs($super)->get('/admin/hidden-system')
            ->assertOk()
            ->assertSee('Kata Kunci Aktif')
            ->assertSee('kunciTampil42');
    }

    public function test_kata_kunci_yang_diganti_lewat_formulir_langsung_terlihat(): void
    {
        // Menutup celah: pengujian sebelumnya memanggil model secara langsung,
        // sehingga jalur formulir yang dipakai pengguna tidak pernah teruji.
        $super = $this->makeUser(['role' => User::ROLE_SUPERADMIN, 'company_id' => null]);

        $this->actingAs($super)->put('/admin/hidden-system', [
            'current_password' => 'password123',
            'keyword' => 'lewatFormulir9',
            'keyword_confirmation' => 'lewatFormulir9',
        ])->assertRedirect(route('admin.hidden'));

        $this->actingAs($super)->get('/admin/hidden-system')
            ->assertOk()
            ->assertSee('lewatFormulir9');
    }

    public function test_kata_kunci_versi_lama_diganti_sekali_lalu_selalu_terlihat(): void
    {
        // Sisa data versi lama berbentuk hash searah; tidak ada cara membacanya,
        // jadi halaman harus mengarahkan ke penggantian, bukan menjanjikan lebih.
        Setting::put(Setting::HIDDEN_KEYWORD, Hash::make('kunciLama77'));

        $super = $this->makeUser(['role' => User::ROLE_SUPERADMIN, 'company_id' => null]);

        $this->actingAs($super)->get('/admin/hidden-system')
            ->assertOk()
            ->assertSee('Kata kunci ini dari versi lama');

        $this->actingAs($super)->put('/admin/hidden-system', [
            'current_password' => 'password123',
            'keyword' => 'kunciBaru88',
            'keyword_confirmation' => 'kunciBaru88',
        ])->assertRedirect(route('admin.hidden'));

        // Sekali diganti, nilainya tampil apa adanya - termasuk saat lupa nanti.
        $this->actingAs($super)->get('/admin/hidden-system')
            ->assertOk()
            ->assertSee('kunciBaru88')
            ->assertDontSee('Kata kunci ini dari versi lama');
    }

    public function test_kata_kunci_bisa_dilihat_dan_ditetapkan_lewat_perintah_server(): void
    {
        // Jalur ini ada supaya kegagalan di sisi web (cache, opcache, sesi)
        // tidak pernah menjadi jalan buntu: server selalu bisa dipakai langsung.
        $this->artisan('drive:hidden-keyword')
            ->expectsOutputToContain('belum pernah diganti')
            ->expectsOutputToContain(Setting::DEFAULT_HIDDEN_KEYWORD)
            ->assertSuccessful();

        $this->artisan('drive:hidden-keyword', ['keyword' => 'lewatServer7'])
            ->assertSuccessful();

        $this->assertSame('lewatServer7', Setting::hiddenKeywordPlain());
        $this->assertTrue(Setting::matchesHiddenKeyword('lewatServer7'));

        $this->artisan('drive:hidden-keyword')
            ->expectsOutputToContain('terenkripsi')
            ->expectsOutputToContain('lewatServer7')
            ->assertSuccessful();

        // Kata kunci bermasalah ditolak sebelum sempat tersimpan.
        $this->artisan('drive:hidden-keyword', ['keyword' => 'ada spasi'])->assertFailed();
        $this->artisan('drive:hidden-keyword', ['keyword' => 'ab'])->assertFailed();

        $this->assertSame('lewatServer7', Setting::hiddenKeywordPlain());
    }

    public function test_tampilan_kata_kunci_tidak_terpengaruh_cache_basi(): void
    {
        Setting::setHiddenKeyword('kunciAsli33');

        // Tiru cache yang tertinggal dari sebelum kode ini dipasang: kalau
        // tampilannya ikut membaca cache, superadmin melihat keadaan lama.
        \Illuminate\Support\Facades\Cache::forever('setting:' . Setting::HIDDEN_KEYWORD, [
            'value' => Hash::make('kunciUsang'),
        ]);

        $this->assertSame('kunciAsli33', Setting::hiddenKeywordPlain());
        $this->assertSame(Setting::STATE_READABLE, Setting::hiddenKeywordState());

        $super = $this->makeUser(['role' => User::ROLE_SUPERADMIN, 'company_id' => null]);

        $this->actingAs($super)->get('/admin/hidden-system')
            ->assertOk()
            ->assertSee('kunciAsli33');
    }

    public function test_kata_kunci_gagal_didekripsi_dibedakan_dari_hash_lama(): void
    {
        Setting::setHiddenKeyword('kunciSah55');
        $this->assertSame(Setting::STATE_READABLE, Setting::hiddenKeywordState());

        // Simulasi APP_KEY berganti: nilai bertanda enc: tetapi isinya rusak.
        Setting::put(Setting::HIDDEN_KEYWORD, 'enc:bukan-ciphertext-yang-sah');

        $this->assertSame(Setting::STATE_UNREADABLE, Setting::hiddenKeywordState());
        $this->assertNull(Setting::hiddenKeywordPlain());
        $this->assertFalse(Setting::matchesHiddenKeyword('kunciSah55'));

        $super = $this->makeUser(['role' => User::ROLE_SUPERADMIN, 'company_id' => null]);

        // Pesannya harus berbeda dari sisa data versi lama: yang ini kata
        // kuncinya benar-benar hilang, bukan sekadar tidak terbaca.
        $this->actingAs($super)->get('/admin/hidden-system')
            ->assertOk()
            ->assertSee('APP_KEY')
            ->assertDontSee('Kata kunci ini dari versi lama');
    }

    public function test_admin_perusahaan_tidak_melihat_kata_kunci_aktif(): void
    {
        Setting::setHiddenKeyword('kunciTampil42');

        $admin = $this->makeUser(['role' => User::ROLE_ADMIN]);

        // Admin masih boleh mengganti kata kunci, tetapi tidak boleh membacanya.
        $this->actingAs($admin)->get('/admin/hidden-system')
            ->assertOk()
            ->assertDontSee('Kata Kunci Aktif')
            ->assertDontSee('kunciTampil42');
    }

    public function test_menu_hidden_system_hanya_untuk_admin(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/admin/hidden-system')->assertForbidden();
        $this->actingAs($user)->put('/admin/hidden-system', [
            'current_password' => 'password123',
            'keyword' => 'apapun123',
            'keyword_confirmation' => 'apapun123',
        ])->assertForbidden();
    }

    public function test_tombol_tampilkan_mengembalikan_file_ke_drive(): void
    {
        $user = $this->makeUser();
        $file = $this->makeHiddenFile($user, 'h.txt');

        $this->actingAs($user)
            ->withSession(['hidden_revealed' => true])
            ->post('/drive/file/' . $file->id . '/toggle-visibility')
            ->assertRedirect(); // form biasa -> redirect, bukan JSON mentah

        $this->assertFalse($file->fresh()->is_hidden);
    }

    // ---------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------

    public function test_admin_bisa_menonaktifkan_user_lewat_form_edit(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $target = $this->makeUser();

        $this->actingAs($admin)->put('/admin/users/' . $target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'user',
            'storage_quota' => 104857600,
            // checkbox tidak dicentang -> hanya hidden 0 yang terkirim
            'is_active' => '0',
        ])->assertRedirect(route('admin.users'));

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_user_biasa_ditolak_dari_area_admin(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_dashboard_admin_tampil_tanpa_error(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Total User');
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Manajemen User');
        $this->actingAs($admin)->get('/admin/users/' . $admin->id . '/edit')->assertOk();
    }

    // ---------------------------------------------------------------
    // Profil & notifikasi
    // ---------------------------------------------------------------

    public function test_avatar_yang_diunggah_benar_benar_tersaji(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('foto-saya.jpg', 120, 120),
        ])->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertNotNull($user->avatarPath(), 'File avatar harus ada di disk');
        $this->assertNotNull($user->avatarUrl());

        // Regresi: URL avatar dulu memakai asset('storage/...') yang bergantung
        // pada symlink public/storage, sehingga gambar tidak tampil di cPanel.
        $this->assertStringNotContainsString('/storage/', $user->avatarUrl());

        $response = $this->actingAs($user)->get($user->avatarUrl());
        $response->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
    }

    public function test_halaman_menampilkan_gambar_avatar_bukan_teks_pengganti(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ])->assertRedirect();

        $url = $user->fresh()->avatarUrl();

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee($url, false);
        $this->actingAs($user)->get('/drive')->assertOk()->assertSee($url, false);
    }

    public function test_avatar_hilang_dari_disk_jatuh_ke_inisial_nama(): void
    {
        $user = $this->makeUser(['name' => 'Budi Santoso', 'avatar' => 'tidak-ada.png']);

        $this->assertNull($user->avatarPath());
        $this->assertNull($user->avatarUrl(), 'Tanpa file, jangan hasilkan URL yang pasti rusak');

        // Tampilan jatuh ke inisial, bukan tag <img> yang pasti gagal dimuat.
        $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();
        $this->assertStringNotContainsString('/avatar/' . $user->id, $html);
        $this->assertMatchesRegularExpression('/>\s*B\s*</', $html);
    }

    public function test_avatar_dari_api_dan_web_memakai_konvensi_yang_sama(): void
    {
        $user = $this->makeUser();
        $user->api_token = 'token-uji-avatar';
        $user->save();

        $this->withHeaders(['Authorization' => 'Bearer token-uji-avatar', 'Accept' => 'application/json'])
            ->post('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('mobile.jpg'),
            ])->assertOk()->assertJson(['success' => true]);

        $user->refresh();

        // Regresi: API dulu menyimpan "avatars/xxx.jpg" sementara web menyimpan
        // nama file polos, sehingga avatar dari mobile tidak tampil di web.
        $this->assertStringNotContainsString('/', $user->avatar);
        $this->assertNotNull($user->avatarPath());

        // Dan hasilnya benar-benar terlihat di halaman web.
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee($user->avatarUrl(), false);
    }

    public function test_url_avatar_berubah_setelah_avatar_diganti(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('satu.png')]);
        $pertama = $user->fresh()->avatarUrl();

        // Mundurkan updated_at agar perubahan timestamp terlihat.
        $user->fresh()->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('dua.png')]);
        $kedua = $user->fresh()->avatarUrl();

        $this->assertNotSame($pertama, $kedua, 'URL harus berubah supaya cache browser tidak menahan avatar lama');
    }

    /**
     * Ambil objek migrasi perbaikan skema dari filenya.
     */
    private function migrasiPerbaikan(): object
    {
        return require database_path('migrations/2026_08_20_000001_ensure_mobile_api_schema.php');
    }

    public function test_pemeriksa_deployment_lolos_saat_semua_lengkap(): void
    {
        $this->artisan('drive:check')
            ->expectsOutputToContain('Guard "api" terpasang')
            ->expectsOutputToContain('Kolom users.api_token ada')
            ->expectsOutputToContain('Tabel settings ada')
            ->assertSuccessful();
    }

    public function test_pemeriksa_deployment_menangkap_guard_api_yang_hilang(): void
    {
        // Inilah kondisi server yang membuat aplikasi menampilkan "Gagal memuat":
        // config/auth.php tanpa guard api, sehingga auth:api melempar error 500.
        config(['auth.guards.api' => null]);

        $this->artisan('drive:check')
            ->expectsOutputToContain('Guard "api" tidak ada di config/auth.php')
            ->assertFailed();
    }

    public function test_pemeriksa_deployment_menangkap_kolom_yang_hilang(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('api_token'));

        $this->artisan('drive:check')
            ->expectsOutputToContain('Kolom users.api_token belum ada')
            ->assertFailed();
    }

    public function test_akun_baru_mendapat_kuota_satu_giga(): void
    {
        $satuGiga = 1073741824;
        $this->assertSame($satuGiga, User::DEFAULT_STORAGE_QUOTA);

        // Lewat web
        $this->post('/register', [
            'name' => 'Web Baru',
            'email' => 'webbaru@dekorasi.test',
            'company_id' => $this->company()->id,
            'company_id' => $this->company()->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ]);
        $this->assertSame($satuGiga, User::where('email', 'webbaru@dekorasi.test')->value('storage_quota'));

        // Lewat aplikasi
        $this->withHeaders(['Accept' => 'application/json'])->post('/api/register', [
            'name' => 'Mobile Baru',
            'email' => 'mobilebaru@dekorasi.test',
            'company_id' => $this->company()->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertOk();
        $this->assertSame($satuGiga, User::where('email', 'mobilebaru@dekorasi.test')->value('storage_quota'));
    }

    public function test_registrasi_lewat_aplikasi_memberi_tahu_admin(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        $this->withHeaders(['Accept' => 'application/json'])->post('/api/register', [
            'name' => 'Aldef',
            'email' => 'aldef2@dekorasi.test',
            'company_id' => $this->company()->id,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'new_registration',
        ]);
    }

    public function test_unggah_melebihi_kuota_menjelaskan_sisa_kuota(): void
    {
        // Kuota 1 MB, file 2 MB.
        $user = $this->makeUser(['storage_quota' => 1048576]);

        $response = $this->actingAs($user)->postJson('/drive/upload', [
            'file' => UploadedFile::fake()->create('besar.pdf', 2048, 'application/pdf'),
            'folder' => '/',
        ]);

        $response->assertStatus(400);

        // Pesan harus menyebut angka, bukan sekadar "gagal".
        $pesan = $response->json('message');
        $this->assertStringContainsString('Kuota penyimpanan tidak cukup', $pesan);
        $this->assertStringContainsString('MB', $pesan);
    }

    public function test_pesan_error_upload_memakai_bahasa_indonesia(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/drive/upload', ['folder' => '/'])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Tidak ada file yang dipilih.');
    }

    public function test_error_server_menyertakan_kode_referensi(): void
    {
        Route::middleware('api')->get('/api/uji-referensi', function () {
            throw new \RuntimeException('kegagalan internal');
        });

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->get('/api/uji-referensi')
            ->assertStatus(500);

        $ref = $response->json('reference');
        $this->assertNotEmpty($ref, 'Balasan harus memuat kode referensi untuk penelusuran log');
        $this->assertStringContainsString($ref, $response->json('message'));
        $this->assertStringNotContainsString('kegagalan internal', $response->getContent());
    }

    public function test_alur_lengkap_daftar_verifikasi_login_dan_buka_drive(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        // 1. Daftar lewat aplikasi
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/register', [
                'name' => 'Aldef',
                'email' => 'aldef@dekorasi.test',
                'company_id' => $this->company()->id,
                'password' => 'rahasia12345',
                'password_confirmation' => 'rahasia12345',
            ])->assertOk()->assertJson(['success' => true]);

        $baru = User::where('email', 'aldef@dekorasi.test')->firstOrFail();
        $this->assertFalse($baru->is_active, 'Akun baru harus menunggu verifikasi');

        // 2. Belum diverifikasi -> login ditolak dengan pesan yang jelas
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/login', ['email' => $baru->email, 'password' => 'rahasia12345'])
            ->assertStatus(403);

        // 3. Admin memverifikasi
        $this->actingAs($admin)->post('/admin/users/' . $baru->id . '/toggle-status');
        $this->assertTrue($baru->fresh()->is_active);

        // 4. Login berhasil dan mengembalikan token
        $login = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/login', ['email' => $baru->email, 'password' => 'rahasia12345'])
            ->assertOk()->json();

        $this->assertNotEmpty($login['token'] ?? null, 'Login harus mengembalikan token');

        // 5. Membuka Drive — inilah panggilan yang gagal di perangkat
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $login['token'],
            'Accept' => 'application/json',
        ])->get('/api/drive')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_login_dan_me_mengirim_persentase_penyimpanan(): void
    {
        $user = $this->makeUser(['storage_quota' => 1073741824, 'storage_used' => 536870912]);

        $login = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()->json();

        // Aplikasi menampilkan bar penyimpanan dari nilai ini; tanpa itu
        // selalu terbaca 0%.
        $this->assertArrayHasKey('storage_percentage', $login['user']);
        $this->assertEqualsWithDelta(50.0, $login['user']['storage_percentage'], 0.1);

        $me = $this->withHeaders([
            'Authorization' => 'Bearer ' . $login['token'],
            'Accept' => 'application/json',
        ])->get('/api/me')->assertOk()->json();

        $this->assertArrayHasKey('storage_percentage', $me['user']);
        $this->assertEqualsWithDelta(50.0, $me['user']['storage_percentage'], 0.1);
    }

    public function test_migrasi_perbaikan_memulihkan_skema_yang_hilang(): void
    {
        $user = $this->makeUser();

        // Tiru kondisi server: kolom api_token dan tabel settings tidak ada,
        // padahal tabel migrations menganggap semuanya sudah dijalankan.
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('api_token'));
        Schema::dropIfExists('settings');

        $this->assertFalse(Schema::hasColumn('users', 'api_token'));
        $this->assertFalse(Schema::hasTable('settings'));

        $this->migrasiPerbaikan()->up();

        $this->assertTrue(Schema::hasColumn('users', 'api_token'), 'Kolom api_token harus dipulihkan');
        $this->assertTrue(Schema::hasTable('settings'), 'Tabel settings harus dipulihkan');

        // Akun lama ikut mendapat token, kalau tidak login mobile tetap gagal.
        $this->assertNotNull($user->fresh()->api_token);
    }

    public function test_migrasi_perbaikan_aman_dijalankan_berulang(): void
    {
        $migrasi = $this->migrasiPerbaikan();

        // Skema sudah lengkap — memanggilnya lagi tidak boleh melempar error.
        $migrasi->up();
        $migrasi->up();

        $this->assertTrue(Schema::hasColumn('users', 'api_token'));
        $this->assertTrue(Schema::hasTable('settings'));
    }

    public function test_registrasi_mobile_jalan_setelah_skema_dipulihkan(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('api_token'));

        // Sebelum diperbaiki: registrasi lewat API gagal (kondisi yang dilaporkan).
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/register', [
                'name' => 'Aldef',
                'email' => 'aldef@dekorasi.test',
                'company_id' => $this->company()->id,
                'password' => 'rahasia12345',
                'password_confirmation' => 'rahasia12345',
            ])->assertStatus(500);

        $this->migrasiPerbaikan()->up();

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/register', [
                'name' => 'Aldef',
                'email' => 'aldef@dekorasi.test',
                'company_id' => $this->company()->id,
                'password' => 'rahasia12345',
                'password_confirmation' => 'rahasia12345',
            ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_api_tidak_membocorkan_detail_internal_saat_error(): void
    {
        // Regresi: saat APP_DEBUG menyala, kegagalan query membuat Laravel
        // mengirim SQL lengkap beserta nilainya ke aplikasi mobile — termasuk
        // hash password pendaftar. Balasan /api/* harus selalu seragam.
        config(['app.debug' => true]);

        Route::middleware('api')->get('/api/uji-ledakan', function () {
            throw new \RuntimeException('SQLSTATE[42S22]: Column not found: rahasia-bocor');
        });

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->get('/api/uji-ledakan');

        $response->assertStatus(500)->assertJson(['success' => false]);

        // Pesan boleh memuat kode referensi, tapi tidak boleh memuat detail teknis.
        $this->assertStringContainsString('Terjadi kesalahan di server', $response->json('message'));

        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('rahasia-bocor', $body);
    }

    public function test_error_validasi_api_tetap_informatif(): void
    {
        // Penyeragaman tidak boleh ikut menelan pesan yang memang untuk pengguna.
        // Kolomnya menerima email maupun username, jadi yang diuji di sini
        // adalah isian yang benar-benar kosong.
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/login', ['password' => 'apa-saja'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/login', ['email' => 'namapengguna'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_route_api_hidup_tanpa_sanctum(): void
    {
        $user = $this->makeUser();
        $user->api_token = 'token-uji-api';
        $user->save();

        // Regresi: bootstrap/app.php pernah memanggil statefulApi() yang
        // membutuhkan Laravel Sanctum. Karena paketnya tidak terpasang, seluruh
        // route /api/* membalas error 500.
        $this->withHeaders(['Accept' => 'application/json'])
            ->get('/api/drive')
            ->assertStatus(401);

        $this->withHeaders([
            'Authorization' => 'Bearer token-uji-api',
            'Accept' => 'application/json',
        ])->get('/api/me')->assertOk()->assertJson(['success' => true]);
    }

    public function test_hapus_avatar_berfungsi(): void
    {
        $user = $this->makeUser(['avatar' => 'contoh.png']);

        $dir = storage_path('app/public/avatars');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/contoh.png', 'x');

        $this->actingAs($user)->delete('/profile/avatar')->assertRedirect();

        $this->assertNull($user->fresh()->avatar);
        $this->assertFileDoesNotExist($dir . '/contoh.png');
    }

    public function test_notifikasi_bisa_dibuka_lewat_tautan(): void
    {
        $user = $this->makeUser();

        $notif = \App\Models\Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'title' => 'Uji',
            'message' => 'Pesan uji',
            'icon' => 'fas fa-bell',
            'color' => 'blue',
            'url' => '/drive',
        ]);

        // Tautan pada daftar notifikasi memakai GET — dulu menghasilkan 405.
        $this->actingAs($user)->get('/notifications/' . $notif->id . '/read')
            ->assertRedirect('/drive');

        $this->assertTrue($notif->fresh()->is_read);
    }

    public function test_halaman_notifikasi_tampil(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Belum Ada Notifikasi');
    }

    // ---------------------------------------------------------------
    // Share
    // ---------------------------------------------------------------

    public function test_penerima_share_mendapat_salinan_di_folder_shared(): void
    {
        $pemilik = $this->makeUser();
        $penerima = $this->makeUser();

        $storage = app(StorageService::class);
        $file = $storage->storeFile(
            UploadedFile::fake()->create('brosur.pdf', 8, 'application/pdf'),
            $pemilik
        );

        $share = FileShare::create([
            'file_id' => $file->id,
            'user_id' => $pemilik->id,
            'share_token' => FileShare::generateToken(),
        ]);

        $this->actingAs($penerima)->postJson('/share/' . $share->share_token . '/download')
            ->assertOk()
            ->assertJson(['success' => true]);

        $salinan = File::where('user_id', $penerima->id)->firstOrFail();
        $this->assertSame('/Shared', $salinan->folder);
        $this->assertFileExists(storage_path('app/drive/' . $salinan->path));
    }

    public function test_share_dengan_password_menolak_password_salah(): void
    {
        $pemilik = $this->makeUser();
        $penerima = $this->makeUser();

        $storage = app(StorageService::class);
        $file = $storage->storeFile(UploadedFile::fake()->create('data.txt', 4, 'text/plain'), $pemilik);

        $share = FileShare::create([
            'file_id' => $file->id,
            'user_id' => $pemilik->id,
            'share_token' => FileShare::generateToken(),
            'password' => Hash::make('buka123'),
        ]);

        $this->actingAs($penerima)
            ->postJson('/share/' . $share->share_token . '/download', ['password' => 'ngawur'])
            ->assertStatus(400);

        $this->actingAs($penerima)
            ->postJson('/share/' . $share->share_token . '/download', ['password' => 'buka123'])
            ->assertOk()->assertJson(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Utilitas
    // ---------------------------------------------------------------

    public function test_semua_halaman_utama_render_tanpa_error(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        foreach (['/login', '/register'] as $url) {
            $this->get($url)->assertOk();
        }

        foreach (['/drive', '/profile', '/notifications', '/admin', '/admin/users', '/admin/hidden-system'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_halaman_profil_menampilkan_tombol_hapus_avatar(): void
    {
        $user = $this->makeUser(['avatar' => 'a.png']);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Hapus Avatar')
            ->assertSee(route('profile.avatar.destroy'));
    }

    public function test_pembersih_nama_file(): void
    {
        $storage = app(StorageService::class);

        $this->assertSame('Laporan-Keuangan.pdf', $storage->sanitizeFilename('Laporan Keuangan.pdf'));
        $this->assertSame('passwd', $storage->sanitizeFilename('../../etc/passwd'));
        $this->assertSame('berkas.tar', $storage->sanitizeFilename('berkas.tar'));
        $this->assertNotSame('', $storage->sanitizeFilename('..'));
    }
}
