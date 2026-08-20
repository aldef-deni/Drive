<?php

namespace Tests\Feature;

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

    private function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
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

    public function test_kata_kunci_disimpan_sebagai_hash(): void
    {
        Setting::setHiddenKeyword('rahasiaKu99');

        $tersimpan = Setting::get(Setting::HIDDEN_KEYWORD);

        $this->assertNotSame('rahasiaKu99', $tersimpan);
        $this->assertStringNotContainsString('rahasiaKu99', (string) $tersimpan);
        $this->assertTrue(Setting::matchesHiddenKeyword('rahasiaKu99'));
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
